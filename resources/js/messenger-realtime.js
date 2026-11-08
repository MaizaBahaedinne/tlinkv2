// Temps réel de la messagerie (Laravel Reverb) : la connexion WebSocket ne sert qu'à signaler « du nouveau »,
// les messages sont ensuite relus par l'interrogation habituelle. Si le temps réel est absent ou qu'il tombe,
// l'interrogation reprend sa cadence normale. Les bibliothèques ne sont chargées que si le serveur l'a activé.

/**
 * @param {string|undefined} raw    configuration JSON transmise par le serveur (data-realtime)
 * @param {string} csrf             jeton CSRF de la page
 * @param {{conversation?: number|null, user?: number|null}} targets  salons à écouter
 * @param {(event: {conversation: number, type: string, by: number|null}) => void} onSignal
 * @returns {{live: () => boolean, follow: (id: number) => void, unfollow: (id: number) => void}}
 */
export function connectRealtime(raw, csrf, targets, onSignal) {
    let live = false;
    let echo = null;
    const wanted = {};
    const bound = {};

    function bind(id) {
        if (echo && wanted[id] && !bound[id]) {
            bound[id] = true;
            echo.private('messenger.conversation.' + id).listen('.messenger.updated', onSignal);
        }
    }

    const api = {
        live: function () { return live; },
        // Les abonnements demandés avant la fin de la connexion sont pris en compte à son établissement.
        follow: function (id) { wanted[id] = true; bind(id); },
        unfollow: function (id) {
            delete wanted[id];
            if (echo && bound[id]) {
                delete bound[id];
                echo.leave('messenger.conversation.' + id);
            }
        },
    };

    if (targets.conversation) { wanted[targets.conversation] = true; }

    let config = null;
    try { config = raw ? JSON.parse(raw) : null; } catch { config = null; }
    if (!config || !config.key || !config.host) { return api; }

    Promise.all([import('laravel-echo'), import('pusher-js')]).then(function (modules) {
        const Echo = modules[0].default;
        window.Pusher = modules[1].default;

        echo = new Echo({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: config.host,
            wsPort: config.port,
            wssPort: config.port,
            forceTLS: !!config.tls,
            enabledTransports: ['ws', 'wss'],
            // Autorisation des salons privés avec la session de la page (cookies + CSRF).
            authorizer: function (channel) {
                return {
                    authorize: function (socketId, callback) {
                        fetch(config.auth, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                            body: new URLSearchParams({ socket_id: socketId, channel_name: channel.name }).toString(),
                        }).then(function (response) {
                            return response.ok ? response.json() : Promise.reject(new Error('auth'));
                        }).then(function (data) { callback(null, data); }).catch(function (error) { callback(error, null); });
                    },
                };
            },
        });

        const connection = echo.connector.pusher.connection;
        let wasConnected = false;
        connection.bind('connected', function () {
            live = true;
            // Les messages arrivés pendant une coupure sont rattrapés dès que la connexion revient.
            if (wasConnected) { onSignal({ conversation: 0, type: 'resync', by: null }); }
            wasConnected = true;
        });
        ['disconnected', 'unavailable', 'failed', 'error'].forEach(function (name) {
            connection.bind(name, function () { live = false; });
        });
        if (targets.user) { echo.private('messenger.user.' + targets.user).listen('.messenger.updated', onSignal); }
        Object.keys(wanted).forEach(bind);
    }).catch(function () { live = false; });

    return api;
}
