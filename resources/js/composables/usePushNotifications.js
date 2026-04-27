function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = window.atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

export function usePushNotifications() {
    const publicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;

    async function subscribe() {
        if (!publicKey) return;
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return;

        const registration   = await navigator.serviceWorker.ready;
        const subscription   = await registration.pushManager.subscribe({
            userVisibleOnly:      true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });

        await window.axios.post(route('push.subscribe'), {
            endpoint: subscription.endpoint,
            keys: {
                p256dh: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('p256dh')))),
                auth:   btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('auth')))),
            },
        });
    }

    return { subscribe };
}
