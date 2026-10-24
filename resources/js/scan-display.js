// PC-Anzeige für den Einlass: zeigt Ergebnisse, die ein gekoppeltes Smartphone (oder ein anderer Scanner) meldet.
export default function scanDisplay(cfg) {
    return {
        stats: cfg.stats,
        last: cfg.last,
        log: cfg.recent.slice().reverse(),
        current: null,
        connected: cfg.connected,
        active: true,
        sound: true,
        audio: null,
        timer: null,
        poll: null,
        failures: 0,
        full: false,

        init() {
            this.poll = setInterval(() => this.fetchFeed(), 1500);
            document.addEventListener('fullscreenchange', () => { this.full = !!document.fullscreenElement; });
        },

        async fetchFeed() {
            if (!cfg.pairingId && !this.log.length && document.visibilityState === 'hidden') return;
            try {
                const res = await fetch(cfg.feed + '?after=' + this.last + '&pairing=' + (cfg.pairingId || 0), { headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error(res.status);
                const data = await res.json();
                this.failures = 0;
                this.stats = data.stats;
                this.connected = data.connected;
                this.active = cfg.pairingId ? data.active : true;
                data.logs.forEach((l) => this.push(l));
            } catch (e) {
                this.failures++;
            }
        },

        push(l) {
            this.last = Math.max(this.last, l.id);
            this.log.unshift(l);
            this.log = this.log.slice(0, 30);
            this.current = l;
            this.beep(l.status);
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.current = null; }, 9000);
        },

        beep(status) {
            if (!this.sound) return;
            try {
                this.audio ||= new (window.AudioContext || window.webkitAudioContext)();
                const osc = this.audio.createOscillator(), gain = this.audio.createGain();
                osc.frequency.value = status === 'ok' ? 880 : 220;
                gain.gain.value = 0.08;
                osc.connect(gain).connect(this.audio.destination);
                osc.start();
                osc.stop(this.audio.currentTime + (status === 'ok' ? 0.12 : 0.35));
            } catch (e) { /* Ton ist optional */ }
        },

        toggleSound() {
            this.sound = !this.sound;
            // Nutzeraktion: Audio freischalten
            try { this.audio ||= new (window.AudioContext || window.webkitAudioContext)(); this.audio.resume(); } catch (e) {}
        },

        fullscreen() {
            if (document.fullscreenElement) document.exitFullscreen();
            else document.documentElement.requestFullscreen?.();
        },

        get percent() { return this.stats.total ? Math.round((this.stats.checked_in / this.stats.total) * 100) : 0; },
        get panelClass() {
            return { ok: 'bg-[#059669] text-white', duplicate: 'bg-[#d97706] text-white', invalid: 'bg-[#dc2626] text-white' }[this.current?.status] || '';
        },
        dot(s) { return { ok: 'bg-[#059669]', duplicate: 'bg-[#d97706]', invalid: 'bg-[#dc2626]' }[s] || 'bg-slate-400'; },
        destroy() { clearInterval(this.poll); clearTimeout(this.timer); },
    };
}
