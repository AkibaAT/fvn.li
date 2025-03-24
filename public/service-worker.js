self.addEventListener('push', function(event) {
    const payload = event.data ? event.data.json() : {};
    event.waitUntil(
        self.registration.showNotification(payload.title || 'New Update', {
            body: payload.body || 'A game you follow has been updated.',
            icon: payload.icon || '/icon.png',
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

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    
    if (event.action === 'view' || !event.action) {
        // Default action is to open the URL from the notification data
        const urlToOpen = event.notification.data.url || '/';
        
        event.waitUntil(
            clients.openWindow(urlToOpen)
        );
    }
});
