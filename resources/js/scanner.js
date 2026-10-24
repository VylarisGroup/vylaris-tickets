import jsQR from 'jsqr';

// Einlass-Scanner: Kamera (QR) oder manuelle Eingabe/Suche. Der Server entscheidet atomar, ob ein Ticket gültig ist.
export default function scanner(config) {
    return {
        urls: config.urls,
        stats: config.stats,
        canUndo: config.canUndo,
        running: false,
        cameraError: null,
        result: null,
        manual: '',
        query: '',
        found: [],
        busy: false,
        lastCode: null,
        lastAt: 0,
        stream: null,
        raf: null,
        audio: null,

        async toggleCamera() {
            this.running ? this.stop() : await this.start();
        },

        async start() {
            this.cameraError = null;
            if (!navigator.mediaDevices?.getUserMedia) {
                this.cameraError = 'Die Kamera ist in diesem Browser nicht verfügbar (HTTPS erforderlich). Nutze die manuelle Eingabe.';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                const video = this.$refs.video;
                video.srcObject = this.stream;
                await video.play();
                this.running = true;
                this.loop();
            } catch (e) {
                this.cameraError = 'Kamerazugriff nicht möglich. Bitte erlauben oder die manuelle Eingabe nutzen.';
            }
        },

        stop() {
            this.running = false;
            cancelAnimationFrame(this.raf);
            this.stream?.getTracks().forEach((t) => t.stop());
            this.stream = null;
        },

        loop() {
            if (!this.running) return;
            const video = this.$refs.video;
            if (video.readyState === video.HAVE_ENOUGH_DATA && !this.busy) {
                const canvas = this.$refs.canvas;
                const w = (canvas.width = video.videoWidth), h = (canvas.height = video.videoHeight);
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(video, 0, 0, w, h);
                const code = jsQR(ctx.getImageData(0, 0, w, h).data, w, h, { inversionAttempts: 'dontInvert' });
                // Gleichen Code 4 Sekunden lang ignorieren, damit ein Ticket nicht mehrfach gemeldet wird.
                if (code?.data && (code.data !== this.lastCode || Date.now() - this.lastAt > 4000)) {
                    this.lastCode = code.data;
                    this.lastAt = Date.now();
                    this.check(code.data);
                }
            }
            this.raf = requestAnimationFrame(() => this.loop());
        },

        async post(url, body = {}) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify(body),
            });
            if (res.status === 419) throw new Error('Sitzung abgelaufen. Bitte Seite neu laden.');
            if (!res.ok && res.status !== 422) throw new Error('Serverfehler (' + res.status + ').');
            return res.json();
        },

        async check(code) {
            if (this.busy) return;
            this.busy = true;
            try {
                this.show(await this.post(this.urls.check, { code }));
            } catch (e) {
                this.show({ status: 'invalid', title: 'Fehler', detail: e.message });
            } finally {
                this.busy = false;
            }
        },

        async submitManual() {
            const code = this.manual.trim();
            if (!code) return;
            await this.check(code);
            this.manual = '';
        },

        async search() {
            if (this.query.trim().length < 2) { this.found = []; return; }
            const res = await fetch(this.urls.search + '?q=' + encodeURIComponent(this.query.trim()), { headers: { Accept: 'application/json' } });
            this.found = res.ok ? (await res.json()).tickets : [];
        },

        async admit(t) {
            this.busy = true;
            try {
                this.show(await this.post(this.urls.admit.replace('__ID__', t.id)));
                await this.search();
            } catch (e) {
                this.show({ status: 'invalid', title: 'Fehler', detail: e.message });
            } finally {
                this.busy = false;
            }
        },

        async undo(t) {
            if (!confirm('Einlösung wirklich zurücksetzen?')) return;
            const data = await this.post(this.urls.undo.replace('__ID__', t.id));
            if (data.stats) this.stats = data.stats;
            await this.search();
        },

        show(data) {
            this.result = data;
            if (data.stats) this.stats = data.stats;
            this.beep(data.status);
            if (navigator.vibrate) navigator.vibrate(data.status === 'ok' ? 60 : [120, 60, 120]);
        },

        beep(status) {
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

        get panelClass() {
            return { ok: 'bg-emerald-600 text-white', duplicate: 'bg-amber-500 text-white', invalid: 'bg-red-600 text-white' }[this.result?.status] || 'bg-slate-200';
        },
        get percent() {
            return this.stats.total ? Math.round((this.stats.checked_in / this.stats.total) * 100) : 0;
        },
        destroy() { this.stop(); },
    };
}
