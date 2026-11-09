// Liens entre le site et les applications installées.
//  - Dans une application (Capacitor) : un lien https du site ouvert par Android (App Links) charge la bonne page dans l'application.
//  - Dans un navigateur Android : si l'application du sous-domaine est installée (navigator.getInstalledRelatedApps), une bannière
//    propose de l'ouvrir. Android interdit d'ouvrir une application sans un geste de l'utilisateur quand l'adresse est tapée dans
//    le navigateur : le bouton « Ouvrir » est donc nécessaire. Les liens touchés depuis une autre application (WhatsApp, SMS…)
//    s'ouvrent directement dans l'application grâce aux App Links.
// Sans effet sur ordinateur ni sur iPhone.

(function () {
    var cap = window.Capacitor;
    var native = !!cap && typeof cap.isNativePlatform === 'function' && cap.isNativePlatform();

    // Domaine commun des applications (link.exemple.org, messenger.exemple.org… => exemple.org).
    function sameFamily(host) {
        var mine = window.location.hostname.split('.').slice(1).join('.');
        return !!mine && (host === mine || host.slice(-(mine.length + 1)) === '.' + mine);
    }

    if (native) {
        var AppPlugin = cap.registerPlugin('App');

        var open = function (value) {
            var link;
            try { link = new URL(value); } catch { return; }
            // Seuls les liens https vers les sous-domaines de la Fondation sont suivis (jamais le schéma de connexion, géré par native-auth.js).
            if (link.protocol !== 'https:' || !sameFamily(link.hostname)) { return; }
            if (link.href !== window.location.href) { window.location.href = link.href; }
        };

        AppPlugin.getLaunchUrl().then(function (launch) { if (launch && launch.url) { open(launch.url); } }).catch(function () { /* sans conséquence */ });
        AppPlugin.addListener('appUrlOpen', function (event) { open(event.url); });
        return;
    }

    var android = /android/i.test(navigator.userAgent);
    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;

    if (!android || standalone || typeof navigator.getInstalledRelatedApps !== 'function') { return; }

    var dismissedAt = parseInt(localStorage.getItem('tlink.openapp.dismissed') || '0', 10);
    if (Date.now() - dismissedAt < 3 * 24 * 3600 * 1000) { return; }

    function banner(app) {
        var target = window.location;
        var intent = 'intent://' + target.host + target.pathname + target.search
            + '#Intent;scheme=https;package=' + app.id
            + ';S.browser_fallback_url=' + encodeURIComponent(target.href) + ';end';

        var bar = document.createElement('div');
        bar.setAttribute('role', 'region');
        bar.setAttribute('aria-label', 'Ouvrir l\'application');
        bar.style.cssText = 'position:fixed;left:0;right:0;top:0;z-index:90;display:flex;align-items:center;gap:10px;padding:10px 12px;background:#fff;border-bottom:1px solid #e2e8f0;box-shadow:0 4px 14px rgba(15,23,42,.12);font:14px/1.3 system-ui,sans-serif;color:#334155';

        var icon = document.querySelector('link[rel="apple-touch-icon"]');
        if (icon) {
            var image = document.createElement('img');
            image.src = icon.href;
            image.alt = '';
            image.style.cssText = 'width:36px;height:36px;border-radius:8px';
            bar.appendChild(image);
        }

        var text = document.createElement('span');
        text.style.cssText = 'flex:1;min-width:0';
        text.textContent = 'Ouvrir dans l\'application ?';

        var go = document.createElement('a');
        go.href = intent;
        go.textContent = 'Ouvrir';
        go.style.cssText = 'background:#d00031;color:#fff;border-radius:10px;padding:8px 14px;font-weight:600;text-decoration:none';

        var close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', 'Fermer');
        close.textContent = '\u00d7';
        close.style.cssText = 'border:0;background:none;font-size:22px;line-height:1;color:#64748b;cursor:pointer';
        close.addEventListener('click', function () {
            localStorage.setItem('tlink.openapp.dismissed', String(Date.now()));
            bar.remove();
        });

        bar.appendChild(text);
        bar.appendChild(go);
        bar.appendChild(close);
        document.body.appendChild(bar);
    }

    navigator.getInstalledRelatedApps().then(function (apps) {
        var app = (apps || []).filter(function (item) { return item.platform === 'play' && item.id; })[0];
        if (app) { banner(app); }
    }).catch(function () { /* navigateur ou site non éligible */ });
})();
