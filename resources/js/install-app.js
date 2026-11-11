// Bouton « Installer l'application » de l'en-tête.
// Affiché uniquement sur smartphone, hors de l'application elle-même (Capacitor, WebView ou PWA lancée depuis l'écran d'accueil)
// et tant que l'application n'est pas détectée comme installée :
//  - Android : navigator.getInstalledRelatedApps() (application native ou PWA déclarées dans le manifeste) ;
//  - iPhone/iPad : Safari ne permet pas de savoir si l'application est installée ; une fois lancée depuis l'écran d'accueil,
//    elle s'ouvre en mode « standalone » et le bouton disparaît de lui-même.
// Un clic déclenche l'installation native quand le navigateur la propose (beforeinstallprompt), sinon une aide pas à pas.

(function () {
    var button = document.querySelector('[data-install-app]');
    var dialog = document.querySelector('[data-install-dialog]');
    if (!button || !dialog) { return; }

    var cap = window.Capacitor;
    if (cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform()) { return; }

    var ua = navigator.userAgent;
    var ios = /iphone|ipad|ipod/i.test(ua) || (/macintosh/i.test(ua) && navigator.maxTouchPoints > 1);
    var android = /android/i.test(ua);
    if (!ios && !android) { return; }
    if (/; wv\)/.test(ua)) { return; }

    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
    if (standalone) { return; }

    var key = 'tlink.install.dismissed';
    var dismissedAt = 0;
    try { dismissedAt = parseInt(localStorage.getItem(key) || '0', 10) || 0; } catch (e) { /* stockage indisponible */ }
    if (Date.now() - dismissedAt < 30 * 24 * 3600 * 1000) { return; }

    var deferred = null;
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferred = event;
    });
    window.addEventListener('appinstalled', function () {
        deferred = null;
        button.hidden = true;
    });

    function reveal() { button.hidden = false; }

    if (android && typeof navigator.getInstalledRelatedApps === 'function') {
        navigator.getInstalledRelatedApps()
            .then(function (apps) { if (!apps || apps.length === 0) { reveal(); } })
            .catch(reveal);
    } else {
        reveal();
    }

    button.addEventListener('click', function () {
        if (deferred) {
            var prompt = deferred;
            deferred = null;
            prompt.prompt();
            prompt.userChoice.then(function (choice) { if (choice && choice.outcome === 'accepted') { button.hidden = true; } }).catch(function () { /* sans conséquence */ });
            return;
        }

        dialog.querySelectorAll('[data-install-steps]').forEach(function (steps) {
            steps.hidden = steps.getAttribute('data-install-steps') !== (ios ? 'ios' : 'android');
        });
        if (typeof dialog.showModal === 'function') { dialog.showModal(); }
    });

    dialog.querySelector('[data-install-close]').addEventListener('click', function () { dialog.close(); });
    dialog.querySelector('[data-install-later]').addEventListener('click', function () {
        try { localStorage.setItem(key, String(Date.now())); } catch (e) { /* stockage indisponible */ }
        button.hidden = true;
        dialog.close();
    });
    dialog.addEventListener('click', function (event) { if (event.target === dialog) { dialog.close(); } });
})();
