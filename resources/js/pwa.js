// Service Worker, Installationshinweis (Android: Prompt, iOS: Anleitung) und Abmelde-Bereinigung.
const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

const store = {
    get(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } },
    set(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* Speicher kann blockiert sein */ } },
};

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return;

    window.addEventListener('load', async () => {
        try {
            const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' });
            // Safari cacht Service Worker hartnäckig: beim Start und bei jeder Rückkehr in die App aktiv prüfen.
            reg.update().catch(() => {});
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') reg.update().catch(() => {});
            });
        } catch (e) { /* PWA-Funktionen sind optional */ }
    });

    // Beim Abmelden zwischengespeicherte Bestellseiten entfernen (geteilte Geräte an der Kasse).
    document.addEventListener('submit', (e) => {
        if (e.target.matches?.('[data-logout]')) navigator.serviceWorker.controller?.postMessage('clear-pages');
    });
}

let deferredPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    window.dispatchEvent(new CustomEvent('vt-installable'));
});
window.addEventListener('appinstalled', () => { deferredPrompt = null; window.dispatchEvent(new CustomEvent('vt-installed')); });

export function installHint() {
    return {
        canPrompt: false,
        ios: false,
        hidden: true,
        get visible() { return !this.hidden && (this.canPrompt || this.ios); },
        init() {
            const dismissedAt = Number(store.get('vt-install-dismissed') || 0);
            const recentlyDismissed = Date.now() - dismissedAt < 30 * 24 * 3600 * 1000;
            if (isStandalone() || recentlyDismissed) return;

            this.ios = isIos();
            this.canPrompt = !!deferredPrompt;
            this.hidden = false;
            window.addEventListener('vt-installable', () => { this.canPrompt = true; });
            window.addEventListener('vt-installed', () => { this.hidden = true; });
        },
        async install() {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            await deferredPrompt.userChoice.catch(() => {});
            deferredPrompt = null;
            this.hidden = true;
        },
        dismiss() { store.set('vt-install-dismissed', String(Date.now())); this.hidden = true; },
    };
}
