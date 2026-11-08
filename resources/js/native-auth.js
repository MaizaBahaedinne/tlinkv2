// Connexion des applications mobiles par le navigateur du téléphone. Google (et d'autres) refusent l'authentification dans une
// WebView : le bouton ouvre donc le navigateur, qui rend la main à l'application par son schéma d'adresse (com.tunivisions.xxx://)
// avec un jeton à usage unique. Le secret (verifier) ne quitte jamais l'application : seul son empreinte (challenge) est envoyée.
// Le navigateur partageant sa session entre les sous-domaines, une application déjà connectée ailleurs s'ouvre sans nouvelle saisie.
// Sans effet dans un navigateur ordinaire.

(function () {
    var cap = window.Capacitor;
    if (!cap || typeof cap.isNativePlatform !== 'function' || !cap.isNativePlatform()) { return; }

    var meta = document.querySelector('meta[name="native-auth"]');
    if (!meta) { return; }

    var startUrl = meta.getAttribute('content');
    var Browser = cap.registerPlugin('Browser');
    var AppPlugin = cap.registerPlugin('App');
    var storeKey = 'tlink.native.verifier';
    var appId = null;

    function hex(bytes) {
        return Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }

    function sha256(text) {
        return crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)).then(function (buf) { return hex(new Uint8Array(buf)); });
    }

    function begin(provider) {
        var verifier = hex(crypto.getRandomValues(new Uint8Array(32)));
        try { localStorage.setItem(storeKey, verifier); } catch { return; }

        sha256(verifier).then(function (challenge) {
            var url = new URL(startUrl, window.location.href);
            url.searchParams.set('app', appId);
            url.searchParams.set('challenge', challenge);
            if (provider) { url.searchParams.set('provider', provider); }
            return Browser.open({ url: url.href });
        });
    }

    AppPlugin.getInfo().then(function (info) {
        appId = info.id;

        // Boutons Google / Facebook / LinkedIn : on passe par le navigateur.
        document.addEventListener('click', function (event) {
            var link = event.target.closest && event.target.closest('a[href*="/auth/"][href$="/redirect"]');
            if (!link) { return; }
            var match = link.getAttribute('href').match(/\/auth\/([^/]+)\/redirect$/);
            if (!match) { return; }
            event.preventDefault();
            begin(match[1]);
        }, true);

        // « Continuer avec mon compte T.Link » : session déjà ouverte dans le navigateur (ou mot de passe saisi là-bas).
        document.querySelectorAll('[data-native-sso]').forEach(function (button) {
            button.hidden = false;
            button.style.display = 'flex';
            button.addEventListener('click', function () { begin(null); });
        });
    });

    // Retour du navigateur : com.tunivisions.xxx://auth?token=…
    AppPlugin.addListener('appUrlOpen', function (event) {
        var link;
        try { link = new URL(event.url); } catch { return; }
        var token = link.searchParams.get('token');
        var verifier = null;
        try { verifier = localStorage.getItem(storeKey); } catch { verifier = null; }
        if (link.host !== 'auth' || !/^[A-Za-z0-9]{64}$/.test(token || '') || !verifier) { return; }

        try { localStorage.removeItem(storeKey); } catch { /* sans conséquence */ }
        Browser.close().catch(function () { /* Android : l'onglet se referme tout seul */ });
        window.location.href = new URL('/auth/native/' + token + '?v=' + verifier, window.location.href).href;
    });
})();
