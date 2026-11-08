// Notifications push des applications mobiles (Capacitor + Firebase). Sans effet dans un navigateur ordinaire.
// Le jeton de l'appareil est envoyé au serveur, qui s'en sert pour prévenir des nouveaux messages ; un appui sur la
// notification ouvre la conversation. Le plugin natif est atteint via le pont Capacitor injecté dans l'application.

(function () {
    var cap = window.Capacitor;
    if (!cap || typeof cap.isNativePlatform !== 'function' || !cap.isNativePlatform()) { return; }

    var meta = document.querySelector('meta[name="push-devices"]');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    if (!meta || !csrf) { return; }

    var url = meta.getAttribute('content');
    var logoutPath = meta.dataset.logout;
    var platform = cap.getPlatform();
    var Push = cap.registerPlugin('PushNotifications');
    var storeKey = 'tlink.push.token';

    function call(method, token) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf.getAttribute('content'), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ token: token, platform: platform }),
        });
    }

    Push.addListener('registration', function (event) {
        try { localStorage.setItem(storeKey, event.value); } catch { /* stockage indisponible */ }
        call('POST', event.value).catch(function () { /* nouvelle tentative au prochain lancement */ });
    });

    Push.addListener('registrationError', function () { /* Firebase non configuré dans cette version de l'application */ });

    // Un appui sur la notification ouvre la page indiquée, à condition qu'elle reste dans l'application.
    Push.addListener('pushNotificationActionPerformed', function (action) {
        var target = action && action.notification && action.notification.data && action.notification.data.url;
        if (!target) { return; }
        try {
            var next = new URL(target, window.location.href);
            var domain = window.location.hostname.split('.').slice(-2).join('.');
            if (next.protocol === 'https:' && (next.hostname === domain || next.hostname.endsWith('.' + domain))) { window.location.href = next.href; }
        } catch { /* adresse invalide */ }
    });

    // À la déconnexion, l'appareil ne doit plus recevoir les notifications de ce compte.
    if (logoutPath) {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            var token = null;
            try { token = localStorage.getItem(storeKey); } catch { token = null; }
            if (token && form && form.getAttribute && (form.getAttribute('action') || '').indexOf(logoutPath) !== -1) { call('DELETE', token).catch(function () { /* sans conséquence */ }); }
        }, true);
    }

    function start() {
        var ready = platform === 'android' && Push.createChannel
            ? Promise.all([
                Push.createChannel({ id: 'messenger', name: 'Messages', description: 'Nouveaux messages', importance: 4, visibility: 0, sound: 'message.wav', vibration: true }),
                Push.createChannel({ id: 'tlink', name: 'Notifications T.Link', description: 'Notifications de la Fondation', importance: 4, visibility: 0, sound: 'notification.wav', vibration: true }),
            ]).catch(function () { /* canaux déjà créés */ })
            : Promise.resolve();

        ready.then(function () { return Push.checkPermissions(); }).then(function (state) {
            return state.receive === 'prompt' || state.receive === 'prompt-with-rationale' ? Push.requestPermissions() : state;
        }).then(function (state) {
            if (state.receive === 'granted') { return Push.register(); }
        }).catch(function () { /* notifications indisponibles */ });
    }

    start();
})();
