// Service worker des notifications push web de T.Link : affiche la notification reçue du serveur et ouvre la page concernée au clic.
// Servi à la racine du site pour couvrir toutes les pages.

self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (event) { event.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (event) {
    var data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = { body: event.data ? event.data.text() : '' };
    }

    var title = data.title || 'T.Link';
    var options = {
        body: data.body || '',
        icon: data.icon || '/app-icons/link/icon-192.png',
        badge: '/app-icons/link/icon-192.png',
        tag: data.tag || undefined,
        renotify: !!data.tag,
        data: { url: data.url || '/notifications' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var target = new URL((event.notification.data && event.notification.data.url) || '/notifications', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
            for (var i = 0; i < windows.length; i++) {
                if (windows[i].url === target && 'focus' in windows[i]) { return windows[i].focus(); }
            }

            return self.clients.openWindow(target);
        })
    );
});
