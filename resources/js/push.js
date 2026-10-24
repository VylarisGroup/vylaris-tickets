// Push-Benachrichtigungen: dieses Gerät an- und abmelden.
// iOS erlaubt Web-Push nur für Apps, die zum Home-Bildschirm hinzugefügt wurden (ab iOS 16.4).
function keyToBytes(base64) {
    const pad = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

export default function pushSettings(cfg) {
    return {
        supported: false,
        needsInstall: false,
        permission: 'default',
        subscribed: false,
        busy: false,
        error: null,

        async init() {
            const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
            this.needsInstall = ios && !standalone;
            this.supported = window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && !this.needsInstall;
            if (!this.supported) return;

            this.permission = Notification.permission;
            try {
                const reg = await navigator.serviceWorker.ready;
                this.subscribed = !!(await reg.pushManager.getSubscription()) && this.permission === 'granted';
            } catch (e) { /* ohne Service Worker nicht verfügbar */ }
        },

        async post(method, body) {
            const res = await fetch(cfg.url, {
                method,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify(body),
            });
            if (!res.ok) throw new Error('Serverfehler (' + res.status + ')');
        },

        async enable() {
            this.busy = true;
            this.error = null;
            try {
                // Nutzeraktion: Erlaubnis anfragen
                this.permission = await Notification.requestPermission();
                if (this.permission !== 'granted') {
                    this.error = 'Benachrichtigungen sind blockiert. Erlaube sie in den Browser- bzw. Geräteeinstellungen für diese Seite.';
                    return;
                }
                const reg = await navigator.serviceWorker.ready;
                let sub = await reg.pushManager.getSubscription();
                sub ||= await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(cfg.key) });
                const json = sub.toJSON();
                await this.post('POST', { endpoint: json.endpoint, keys: json.keys, contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0] });
                this.subscribed = true;
                window.location.reload();
            } catch (e) {
                this.error = 'Die Anmeldung ist fehlgeschlagen: ' + (e?.message || 'unbekannter Fehler');
            } finally {
                this.busy = false;
            }
        },

        async disable() {
            this.busy = true;
            this.error = null;
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (sub) {
                    await this.post('DELETE', { endpoint: sub.endpoint });
                    await sub.unsubscribe();
                }
                this.subscribed = false;
                window.location.reload();
            } catch (e) {
                this.error = 'Das Abmelden ist fehlgeschlagen: ' + (e?.message || 'unbekannter Fehler');
            } finally {
                this.busy = false;
            }
        },
    };
}
