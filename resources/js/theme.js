// Dunkel ist Standard. Gespeichert wird nur die Wahl (dark | light | system); der Speicher kann blockiert sein.
const KEY = 'vt-theme';
const COLORS = { dark: '#080c19', light: '#f3f5fb' };

const read = () => { try { return localStorage.getItem(KEY) || 'dark'; } catch (e) { return 'dark'; } };
const write = (v) => { try { localStorage.setItem(KEY, v); } catch (e) { /* ignorieren */ } };
const prefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

export function applyTheme(mode) {
    const theme = mode === 'system' ? (prefersDark() ? 'dark' : 'light') : mode;
    const root = document.documentElement;
    root.dataset.theme = theme;
    root.dataset.mode = mode;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', COLORS[theme]);
}

export function themeToggle() {
    return {
        mode: 'dark',
        init() {
            this.mode = read();
            applyTheme(this.mode);
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', () => {
                if (this.mode === 'system') applyTheme('system');
            });
        },
        cycle() {
            this.mode = { dark: 'light', light: 'system', system: 'dark' }[this.mode] || 'dark';
            write(this.mode);
            applyTheme(this.mode);
        },
        get label() {
            return { dark: 'Dunkel', light: 'Hell', system: 'Automatisch' }[this.mode];
        },
    };
}
