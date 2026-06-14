var CACHE_NAME = 'fvn-cache-v2';

self.addEventListener('install', function (event) {
    event.waitUntil(self.skipWaiting());
});

// Cache game page responses for offline browsing
self.addEventListener('fetch', function (event) {
    // Only cache GET requests for game pages and static assets
    if (event.request.method !== 'GET') return;

    var url = new URL(event.request.url);

    // Cache game page responses and static assets
    var shouldCache = url.pathname.startsWith('/games/') ||
        url.pathname.startsWith('/build/') ||
        url.pathname === '/';

    if (!shouldCache) return;

    event.respondWith(
        caches.open(CACHE_NAME).then(function (cache) {
            return fetch(event.request).then(function (response) {
                // Only cache successful responses
                if (response.status === 200) {
                    cache.put(event.request, response.clone());
                }
                return response;
            }).catch(function () {
                // Return cached version when offline
                return cache.match(event.request);
            });
        })
    );
});

// Clean old caches on activation
self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (cacheNames) {
            return Promise.all(
                cacheNames.filter(function (name) {
                    return name !== CACHE_NAME;
                }).map(function (name) {
                    return caches.delete(name);
                })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('push', function (event) {
    const payload = event.data ? event.data.json() : {};
    event.waitUntil(
        self.registration.showNotification(payload.title || 'New Update', {
            body: payload.body || 'A game you follow has been updated.',
            icon: payload.icon || '/icon-192.png',
            badge: payload.badge || '/badge.png',
            tag: payload.tag || 'game-update',
            data: payload.data || {},
            actions: payload.actions || [
                {
                    action: 'view',
                    title: 'View Update',
                }
            ]
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    if (event.action === 'view' || !event.action) {
        // Default action is to open the URL from the notification data
        const urlToOpen = event.notification.data.url || '/';

        event.waitUntil(
            clients.openWindow(urlToOpen)
        );
    }
});
