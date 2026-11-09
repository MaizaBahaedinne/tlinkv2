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
    var testButton = box ? box.querySelector('[data-web-push-test]') : null;
    var testUrl = meta.dataset.test;

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
        if (testButton) { testButton.hidden = state !== 'on'; }
    }

    if (!supported) { render('unsupported'); return; }

    function request(method, payload, target) {
        return fetch(target || url, {
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

    // Notification de test affichée tout de suite : confirme à l'utilisateur que tout fonctionne.
    function welcome() {
        return request('POST', {}, testUrl).catch(function () { /* sans conséquence */ });
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
        // Sur le feed, l'activation est obligatoire : popup bloquante tant que ce navigateur n'est pas abonné (le navigateur n'accepte
        // de demander l'autorisation qu'après un clic). Ailleurs, simple invitation, refus mémorisé 14 jours.
        function invite(registration) {
            var required = meta.dataset.required === '1';
            var dismissedAt = parseInt(localStorage.getItem('tlink.webpush.dismissed') || '0', 10);

            if (box || document.querySelector('[data-web-push-popup]')) { return; }
            if (!required && (Notification.permission !== 'default' || Date.now() - dismissedAt < 14 * 24 * 3600 * 1000)) { return; }

            registration.pushManager.getSubscription().then(function (subscription) {
                if (subscription) { return; }

                var overlay = document.createElement('div');
                overlay.setAttribute('data-web-push-popup', '');
                overlay.setAttribute('role', 'dialog');
                overlay.setAttribute('aria-modal', 'true');
                overlay.setAttribute('aria-label', 'Notifications');
                overlay.style.cssText = required
                    ? 'position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.7)'
                    : 'position:fixed;right:16px;bottom:16px;z-index:60;max-width:340px';

                var card = document.createElement('div');
                card.style.cssText = 'width:100%;max-width:400px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.25);font:14px/1.5 system-ui,sans-serif;color:#334155';
                card.innerHTML = '<p style="margin:0 0 6px;font-size:17px;font-weight:700;color:#0f172a">Activer les notifications</p>'
                    + '<p data-text style="margin:0 0 16px">Pour utiliser T.Link, autorise les notifications : tu seras prévenu(e) des nouveaux messages et des alertes, même lorsque le site est fermé.</p>'
                    + '<div style="display:flex;gap:8px;justify-content:flex-end">'
                    + (required ? '' : '<button type="button" data-later style="border:1px solid #cbd5e1;background:#fff;border-radius:10px;padding:9px 14px;cursor:pointer">Plus tard</button>')
                    + '<button type="button" data-accept style="border:0;background:#d00031;color:#fff;border-radius:10px;padding:9px 16px;font-weight:600;cursor:pointer">Activer les notifications</button></div>';
                overlay.appendChild(card);
                document.body.appendChild(overlay);

                var text = card.querySelector('[data-text]');
                var accept = card.querySelector('[data-accept]');
                var later = card.querySelector('[data-later]');

                function blocked() {
                    text.textContent = 'Les notifications sont bloquées pour ce site. Clique sur le cadenas à gauche de l\'adresse, autorise les notifications, puis réessaie.';
                    accept.textContent = 'Réessayer';
                }

                if (Notification.permission === 'denied') { blocked(); }

                if (later) {
                    later.addEventListener('click', function () {
                        localStorage.setItem('tlink.webpush.dismissed', String(Date.now()));
                        overlay.remove();
                    });
                }

                accept.addEventListener('click', function () {
                    accept.disabled = true;

                    Notification.requestPermission().then(function (permission) {
                        if (permission !== 'granted') { blocked(); return null; }

                        return subscribe(registration).then(function () { overlay.remove(); welcome(); });
                    }).catch(function () {
                        text.textContent = 'Activation impossible pour le moment. Vérifie ta connexion et réessaie.';
                        accept.textContent = 'Réessayer';
                    }).then(function () { accept.disabled = false; });
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

        if (testButton) {
            testButton.addEventListener('click', function () {
                testButton.disabled = true;
                welcome().then(function () { setTimeout(function () { testButton.disabled = false; }, 4000); });
            });
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                toggle.disabled = true;

                registration.pushManager.getSubscription().then(function (subscription) {
                    if (subscription) {
                        return request('DELETE', { endpoint: subscription.endpoint }).catch(function () { /* sans conséquence */ }).then(function () { return subscription.unsubscribe(); });
                    }

                    return Notification.requestPermission().then(function (permission) {
                        if (permission === 'granted') { return subscribe(registration).then(welcome); }
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
