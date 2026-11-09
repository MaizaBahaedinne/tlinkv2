// Notifications push dans le navigateur (Web Push). Sans effet dans les applications mobiles (elles utilisent native-push.js).
// Enregistre le service worker, rattache l'abonnement du navigateur au compte connecté et pilote le bouton d'activation
// ([data-web-push]) de la page des notifications.

(function () {
    var meta = document.querySelector('meta[name="web-push"]');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    if (!meta || !csrf) { return; }

    if (window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform()) { return; }

    var url = meta.getAttribute('content');
    var publicKey = meta.dataset.key;
    var logoutPath = meta.dataset.logout;
    var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    var box = document.querySelector('[data-web-push]');
    var status = box ? box.querySelector('[data-web-push-status]') : null;
    var toggle = box ? box.querySelector('[data-web-push-toggle]') : null;

    function render(state) {
        if (!box) { return; }
        var texts = {
            unsupported: 'Ce navigateur ne gère pas les notifications push.',
            denied: 'Les notifications sont bloquées : autorise-les dans les réglages du site de ton navigateur.',
            on: 'Les notifications sont activées sur ce navigateur.',
            off: 'Reçois les nouveaux messages et alertes même lorsque T.Link est fermé.',
            error: 'Activation impossible pour le moment, réessaie plus tard.',
        };
        box.hidden = false;
        if (status) { status.textContent = texts[state]; }
        if (toggle) {
            toggle.hidden = state === 'unsupported' || state === 'denied';
            toggle.disabled = false;
            toggle.textContent = state === 'on' ? 'Désactiver' : 'Activer les notifications';
            toggle.dataset.state = state;
        }
    }

    if (!supported) { render('unsupported'); return; }

    function request(method, payload) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf.getAttribute('content'), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
        });
    }

    function keyBytes(base64) {
        var padded = (base64 + '='.repeat((4 - base64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(padded);
        var bytes = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) { bytes[i] = raw.charCodeAt(i); }
        return bytes;
    }

    function send(subscription) {
        var json = subscription.toJSON();
        return request('POST', { endpoint: json.endpoint, keys: json.keys, contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0] });
    }

    function subscribe(registration) {
        return registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(publicKey) }).then(function (subscription) {
            return send(subscription).then(function (response) {
                if (!response.ok) { return subscription.unsubscribe().then(function () { throw new Error('refuse'); }); }
                return subscription;
            });
        });
    }

    navigator.serviceWorker.register('/sw.js').then(function () { return navigator.serviceWorker.ready; }).then(function (registration) {
        // Invitation affichée une fois connecté (le navigateur n'accepte de demander l'autorisation qu'après un clic) ; refus mémorisé 14 jours.
        function invite(registration) {
            var dismissedAt = parseInt(localStorage.getItem('tlink.webpush.dismissed') || '0', 10);

            if (box || Notification.permission !== 'default' || Date.now() - dismissedAt < 14 * 24 * 3600 * 1000) { return; }

            registration.pushManager.getSubscription().then(function (subscription) {
                if (subscription) { return; }

                var banner = document.createElement('div');
                banner.setAttribute('role', 'dialog');
                banner.setAttribute('aria-label', 'Notifications');
                banner.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:60;max-width:340px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px;box-shadow:0 10px 30px rgba(15,23,42,.18);font:14px/1.45 system-ui,sans-serif;color:#334155';
                banner.innerHTML = '<p style="margin:0 0 12px"><strong style="color:#0f172a">Activer les notifications ?</strong><br>Reçois les nouveaux messages et alertes de T.Link, même lorsque le site est fermé.</p>'
                    + '<div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" data-later style="border:1px solid #cbd5e1;background:#fff;border-radius:10px;padding:8px 12px;cursor:pointer">Plus tard</button>'
                    + '<button type="button" data-accept style="border:0;background:#d00031;color:#fff;border-radius:10px;padding:8px 12px;font-weight:600;cursor:pointer">Activer</button></div>';
                document.body.appendChild(banner);

                banner.querySelector('[data-later]').addEventListener('click', function () {
                    localStorage.setItem('tlink.webpush.dismissed', String(Date.now()));
                    banner.remove();
                });
                banner.querySelector('[data-accept]').addEventListener('click', function () {
                    Notification.requestPermission().then(function (permission) {
                        if (permission === 'granted') { return subscribe(registration); }
                    }).catch(function () { /* sans conséquence */ }).then(function () { banner.remove(); });
                });
            });
        }

        function refresh() {
            return registration.pushManager.getSubscription().then(function (subscription) {
                if (Notification.permission === 'denied') { render('denied'); return; }
                render(subscription ? 'on' : 'off');
            });
        }

        registration.pushManager.getSubscription().then(function (subscription) {
            // Navigateur déjà autorisé : l'abonnement est (re)créé sans rien demander, et rattaché au compte connecté.
            if (Notification.permission === 'granted' && !subscription) { return subscribe(registration); }
            if (subscription && !sessionStorage.getItem('tlink.webpush.synced')) {
                sessionStorage.setItem('tlink.webpush.synced', '1');
                return send(subscription);
            }
        }).catch(function () { /* sans conséquence */ }).then(refresh).then(function () { invite(registration); });

        if (toggle) {
            toggle.addEventListener('click', function () {
                toggle.disabled = true;

                registration.pushManager.getSubscription().then(function (subscription) {
                    if (subscription) {
                        return request('DELETE', { endpoint: subscription.endpoint }).catch(function () { /* sans conséquence */ }).then(function () { return subscription.unsubscribe(); });
                    }

                    return Notification.requestPermission().then(function (permission) {
                        if (permission === 'granted') { return subscribe(registration); }
                    });
                }).then(refresh).catch(function () { render('error'); });
            });
        }

        // À la déconnexion, l'abonnement est détaché du compte (il reste actif sur le navigateur pour le prochain).
        if (logoutPath) {
            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (form && form.getAttribute && (form.getAttribute('action') || '').indexOf(logoutPath) !== -1) {
                    registration.pushManager.getSubscription().then(function (subscription) {
                        if (subscription) { sessionStorage.removeItem('tlink.webpush.synced'); request('DELETE', { endpoint: subscription.endpoint }).catch(function () { /* sans conséquence */ }); }
                    });
                }
            });
        }
    }).catch(function () { render('error'); });
})();
