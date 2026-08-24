self.addEventListener('push', (event) => {
    const payload = event.data.json();
    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body,
            data: { orderId: payload.data?.order_id, url: payload.data?.url },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const { orderId, url } = event.notification.data || {};

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // A tab from this app is already open — focus it and dispatch the
            // same event the in-app bell's notification item click dispatches,
            // instead of navigating/reloading anything.
            const client = windowClients.find((c) => c.url.startsWith(self.registration.scope));
            if (client) {
                client.focus();
                client.postMessage({ type: 'show-order-detail', orderId });
                return;
            }

            return clients.openWindow(url ?? self.registration.scope);
        })
    );
});
