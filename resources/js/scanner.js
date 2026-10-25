import jsQR from 'jsqr';

// Einlass-Scanner: Kamera (QR) oder manuelle Eingabe/Suche. Der Server entscheidet atomar, ob ein Ticket gültig ist.
//
// PWA-Eigenheiten, die hier berücksichtigt sind:
// - iOS beendet den Kamerastrom, sobald die App in den Hintergrund geht -> bei Rückkehr neu starten.
// - iOS erlaubt Ton erst nach einer Nutzeraktion -> AudioContext beim Tippen auf «Kamera starten» anlegen.
// - Der Bildschirm soll während des Scannens nicht ausgehen (Wake Lock, iOS 16.4+/Android).
// - Android/Chrome hat einen schnellen, nativen QR-Detektor (BarcodeDetector); jsQR dient als Ersatz (iOS).
// - Kein Seitenwechsel im Scanner, damit die Kameraerlaubnis nicht erneut abgefragt wird.
export default function scanner(config) {
    return {
        urls: config.urls,
        stats: config.stats,
        canUndo: config.canUndo,
        running: false,
        wantRunning: false,
        cameraError: null,
        result: null,
        mode: 'in',
        manual: '',
        query: '',
        found: [],
        busy: false,
        scanning: false,
        lastCode: null,
        lastAt: 0,
        stream: null,
        raf: null,
        audio: null,
        wakeLock: null,
        detector: null,
        wedge: null,

        init() {
            this.listenWedge();
            if ('BarcodeDetector' in window) {
                try { this.detector = new window.BarcodeDetector({ formats: ['qr_code'] }); } catch (e) { this.detector = null; }
            }
            document.addEventListener('visibilitychange', () => this.onVisibility());
            window.addEventListener('pagehide', () => this.stop(true));
            // Gekoppelter Modus: regelmässig melden, dass das Handy online ist (die PC-Anzeige zeigt «verbunden»).
            if (this.urls.ping) {
                const ping = () => { if (document.visibilityState === 'visible') this.post(this.urls.ping).then((d) => { if (d.stats) this.stats = d.stats; }).catch(() => {}); };
                setInterval(ping, 8000);
            }
        },

        // Handscanner (USB/Kabel, «Tastatur-Modus»): Er tippt den Code in wenigen Millisekunden und schickt Enter.
        // Wir erkennen das am Tempo, egal wo der Fokus liegt, und nehmen die Zeichen aus dem Eingabefeld wieder heraus.
        // Es zählt die physische Taste (event.code), damit Tastaturlayout (z. B. Y/Z) und Feststelltaste keine Rolle spielen.
        listenWedge() {
            const MAX_GAP = 80; // ms zwischen zwei Zeichen; Menschen tippen deutlich langsamer
            const MIN_LEN = 6;
            let buf = '';
            let last = 0;
            let target = null;
            let before = '';
            let idle = null;

            const reset = () => { buf = ''; target = null; clearTimeout(idle); };
            const fire = () => {
                const code = buf;
                if (target && 'value' in target) {
                    target.value = before;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                }
                reset();
                this.wedge = { last: code, at: Date.now() };
                this.check(code);
            };

            window.addEventListener('keydown', (e) => {
                if (e.ctrlKey || e.altKey || e.metaKey || e.isComposing) return;

                const now = Date.now();
                if (now - last > MAX_GAP) reset();
                last = now;

                if (e.key === 'Enter' || e.key === 'Tab') {
                    if (buf.length >= MIN_LEN) { e.preventDefault(); e.stopPropagation(); fire(); }
                    else reset();
                    return;
                }

                const ch = this.wedgeChar(e);
                if (!ch) { if (e.key.length === 1) reset(); return; }

                if (buf === '') {
                    target = ['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName) ? document.activeElement : null;
                    before = target ? target.value : '';
                }
                buf += ch;

                // Scanner ohne Enter-Suffix: nach kurzer Pause den schnell getippten Code übernehmen.
                clearTimeout(idle);
                if (buf.length >= 12) idle = setTimeout(() => { if (buf.length >= 12) fire(); }, 150);
            }, true);
        },

        wedgeChar(e) {
            const m = /^(?:Key([A-Z])|Digit(\d)|Numpad(\d))$/.exec(e.code || '');
            if (m) return (m[1] || m[2] || m[3]).toUpperCase();
            if (!e.code && /^[A-Za-z0-9]$/.test(e.key)) return e.key.toUpperCase();
            return null;
        },

        async toggleCamera() {
            if (this.running) { this.wantRunning = false; this.stop(); return; }
            this.wantRunning = true;
            // Nutzeraktion: Ton freischalten (iOS)
            try {
                this.audio ||= new (window.AudioContext || window.webkitAudioContext)();
                await this.audio.resume();
            } catch (e) { /* Ton ist optional */ }
            await this.start();
        },

        async start() {
            this.cameraError = null;
            if (!navigator.mediaDevices?.getUserMedia) {
                this.cameraError = 'Die Kamera ist in diesem Browser nicht verfügbar (HTTPS erforderlich). Nutze die manuelle Eingabe.';
                this.wantRunning = false;
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false,
                });
                // Beendet das Gerät den Strom (z. B. Anruf, andere App), den Zustand sauber zurücksetzen.
                this.stream.getVideoTracks().forEach((t) => t.addEventListener('ended', () => this.stop(true)));

                const video = this.$refs.video;
                video.setAttribute('playsinline', '');
                video.muted = true;
                video.srcObject = this.stream;
                await video.play();
                this.running = true;
                this.requestWakeLock();
                this.loop();
            } catch (e) {
                this.wantRunning = false;
                this.cameraError = ({
                    NotAllowedError: 'Kamerazugriff verweigert. Bitte in den Geräteeinstellungen erlauben (iPhone: Einstellungen → Safari bzw. der App → Kamera) und neu starten.',
                    NotFoundError: 'Es wurde keine Kamera gefunden.',
                    NotReadableError: 'Die Kamera wird gerade von einer anderen App verwendet.',
                    OverconstrainedError: 'Die Kamera unterstützt die gewünschte Einstellung nicht.',
                })[e?.name] || 'Die Kamera konnte nicht gestartet werden. Nutze die manuelle Eingabe.';
            }
        },

        // keepWanted: Kamera wurde vom System beendet, soll aber bei Rückkehr wieder starten.
        stop(keepWanted = false) {
            this.running = false;
            if (!keepWanted) this.wantRunning = false;
            cancelAnimationFrame(this.raf);
            this.stream?.getTracks().forEach((t) => t.stop());
            this.stream = null;
            this.releaseWakeLock();
        },

        async onVisibility() {
            if (document.visibilityState === 'hidden') {
                if (this.running) this.stop(true);
                return;
            }
            // Zurück in der App: Kamera wieder starten, falls sie vorher lief.
            if (this.wantRunning && !this.running) await this.start();
            else if (this.running) this.requestWakeLock();
        },

        async requestWakeLock() {
            try {
                if ('wakeLock' in navigator && !this.wakeLock) {
                    this.wakeLock = await navigator.wakeLock.request('screen');
                    this.wakeLock.addEventListener('release', () => { this.wakeLock = null; });
                }
            } catch (e) { /* z. B. Energiesparmodus: ohne Wake Lock weiterarbeiten */ }
        },

        releaseWakeLock() {
            this.wakeLock?.release().catch(() => {});
            this.wakeLock = null;
        },

        loop() {
            if (!this.running) return;
            const video = this.$refs.video;

            if (video.readyState === video.HAVE_ENOUGH_DATA && !this.busy && !this.scanning) {
                this.scanning = true;
                this.detect(video)
                    .then((value) => {
                        // Gleichen Code 4 Sekunden lang ignorieren, damit ein Ticket nicht mehrfach gemeldet wird.
                        if (value && (value !== this.lastCode || Date.now() - this.lastAt > 4000)) {
                            this.lastCode = value;
                            this.lastAt = Date.now();
                            this.check(value);
                        }
                    })
                    .catch(() => {})
                    .finally(() => { this.scanning = false; });
            }
            this.raf = requestAnimationFrame(() => this.loop());
        },

        async detect(video) {
            if (this.detector) {
                try {
                    const codes = await this.detector.detect(video);
                    return codes[0]?.rawValue || null;
                } catch (e) { this.detector = null; /* auf jsQR zurückfallen */ }
            }
            // Bild für jsQR verkleinern (schneller, ausreichend für Ticket-QR)
            const canvas = this.$refs.canvas;
            const scale = Math.min(1, 640 / video.videoWidth);
            const w = (canvas.width = Math.round(video.videoWidth * scale));
            const h = (canvas.height = Math.round(video.videoHeight * scale));
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(video, 0, 0, w, h);
            return jsQR(ctx.getImageData(0, 0, w, h).data, w, h, { inversionAttempts: 'dontInvert' })?.data || null;
        },

        async post(url, body = {}) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify(body),
            });
            if (res.status === 419) throw new Error('Sitzung abgelaufen. Bitte Seite neu laden.');
            if (res.status === 401) throw new Error('Nicht mehr angemeldet. Bitte Seite neu laden.');
            if (!res.ok && res.status !== 422) throw new Error('Serverfehler (' + res.status + ').');
            return res.json();
        },

        async check(code) {
            if (this.busy) return;
            this.busy = true;
            try {
                this.show(await this.post(this.urls.check, { code, mode: this.mode }));
            } catch (e) {
                this.show({ status: 'invalid', title: navigator.onLine ? 'Fehler' : 'Keine Verbindung', detail: navigator.onLine ? e.message : 'Ticket nicht geprüft. Bitte Verbindung prüfen und erneut scannen.' });
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
            if (!this.urls.search || this.query.trim().length < 2) { this.found = []; return; }
            try {
                const res = await fetch(this.urls.search + '?q=' + encodeURIComponent(this.query.trim()), { headers: { Accept: 'application/json' } });
                this.found = res.ok ? (await res.json()).tickets : [];
            } catch (e) { this.found = []; }
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
            try {
                const data = await this.post(this.urls.undo.replace('__ID__', t.id));
                if (data.stats) this.stats = data.stats;
                await this.search();
            } catch (e) {
                this.show({ status: 'invalid', title: 'Fehler', detail: e.message });
            }
        },

        show(data) {
            this.result = data;
            if (data.stats) this.stats = data.stats;
            this.beep(data.status);
            if (navigator.vibrate) navigator.vibrate(data.status === 'ok' ? 60 : [120, 60, 120]);
        },

        beep(status) {
            try {
                if (!this.audio) return;
                const osc = this.audio.createOscillator(), gain = this.audio.createGain();
                osc.frequency.value = status === 'ok' ? 880 : 220;
                gain.gain.value = 0.08;
                osc.connect(gain).connect(this.audio.destination);
                osc.start();
                osc.stop(this.audio.currentTime + (status === 'ok' ? 0.12 : 0.35));
            } catch (e) { /* Ton ist optional */ }
        },

        get panelClass() {
            return { ok: 'bg-[#059669] text-white', out: 'bg-[#2563eb] text-white', duplicate: 'bg-[#d97706] text-white', invalid: 'bg-[#dc2626] text-white' }[this.result?.status] || 'bg-slate-200';
        },
        get percent() {
            return this.stats.total ? Math.round((this.stats.checked_in / this.stats.total) * 100) : 0;
        },
        destroy() { this.stop(); },
    };
}
