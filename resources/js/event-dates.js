// Datumsauswahl für Events: Datum und Uhrzeit getrennt, Schnellwahl, Dauer, Einlass relativ zum Beginn, mehrtägig
// und eine Zusammenfassung mit Warnungen. Schreibt Beginn, Ende und Einlass als «JJJJ-MM-TTTHH:MM» in versteckte Felder.
const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const hm = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
const parse = (v) => { if (!v) return null; const d = new Date(v); return Number.isNaN(d.getTime()) ? null : d; };
const at = (date, time) => (date && time ? new Date(`${date}T${time}`) : null);
const addMin = (d, m) => new Date(d.getTime() + m * 60000);
const fmtDay = (d) => d.toLocaleDateString('de-CH', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

export default function eventDates(cfg) {
    const start = parse(cfg.starts);
    const end = parse(cfg.ends);
    const doors = parse(cfg.doors);

    // Ende am Folgetag vor der Startzeit (Party 22:00 bis 02:00) gilt nicht als mehrtägig.
    const overnight = start && end && ymd(end) !== ymd(start) && end.getTime() - start.getTime() < 24 * 3600000 && hm(end) <= hm(start);

    const doorsDiff = start && doors ? Math.round((start.getTime() - doors.getTime()) / 60000) : null;

    return {
        presets: [15, 30, 45, 60, 90],
        durations: [60, 120, 180, 240],
        date: start ? ymd(start) : '',
        time: start ? hm(start) : '',
        multi: !!(start && end && ymd(end) !== ymd(start) && !overnight),
        endDate: end ? ymd(end) : '',
        endTime: end ? hm(end) : '',
        doorsMode: doors ? ([15, 30, 45, 60, 90].includes(doorsDiff) ? String(doorsDiff) : 'custom') : 'none',
        doorsTime: doors ? hm(doors) : '',
        today: ymd(new Date()),

        init() {
            this.$watch(() => [this.startsAt, this.endsAt, this.doorsAt], () => {
                this.$dispatch('dates-changed', { starts: this.startsAt, ends: this.endsAt, doors: this.doorsAt });
            });
            this.$dispatch('dates-changed', { starts: this.startsAt, ends: this.endsAt, doors: this.doorsAt });
        },

        get startDt() { return at(this.date, this.time); },
        get startsAt() { return this.startDt ? `${this.date}T${this.time}` : ''; },

        get endDt() {
            if (!this.endTime) return null;
            if (this.multi) return at(this.endDate, this.endTime);
            const e = at(this.date, this.endTime);
            // Endzeit vor oder gleich Beginn: das Event endet am Folgetag.
            return e && this.startDt && e <= this.startDt ? addMin(e, 1440) : e;
        },
        get endsAt() { const e = this.endDt; return e ? `${ymd(e)}T${hm(e)}` : ''; },

        get doorsDt() {
            if (!this.startDt || this.doorsMode === 'none') return null;
            if (this.doorsMode === 'custom') return at(this.date, this.doorsTime);
            return addMin(this.startDt, -Number(this.doorsMode));
        },
        get doorsAt() { const d = this.doorsDt; return d ? `${ymd(d)}T${hm(d)}` : ''; },

        // --- Schnellwahl ---
        setDate(d) { this.date = ymd(d); if (this.multi && this.endDate < this.date) this.endDate = this.date; },
        quickDay(offset) { const d = new Date(); d.setDate(d.getDate() + offset); this.setDate(d); },
        nextWeekday(wd) { const d = new Date(); d.setDate(d.getDate() + ((wd - d.getDay() + 7) % 7 || 7)); this.setDate(d); },
        setDuration(min) {
            if (!this.startDt) return;
            this.multi = false;
            const e = addMin(this.startDt, min);
            // Über Mitternacht: Endzeit ohne Datum genügt, das Ende wird automatisch auf den Folgetag gelegt.
            this.endTime = hm(e);
            if (ymd(e) !== this.date && min >= 1440) { this.multi = true; this.endDate = ymd(e); }
        },
        allDay() { this.time ||= '00:00'; this.multi = false; this.endTime = '23:59'; },
        clearEnd() { this.endTime = ''; this.multi = false; this.endDate = ''; },
        toggleMulti() {
            this.multi = !this.multi;
            if (this.multi) { this.endDate ||= this.date; this.endTime ||= this.time; }
        },
        get durationMin() { return this.startDt && this.endDt ? Math.round((this.endDt - this.startDt) / 60000) : null; },
        isDuration(min) { return this.durationMin === min; },

        // --- Anzeige ---
        get durationText() {
            const m = this.durationMin;
            if (m === null || m <= 0) return '';
            const d = Math.floor(m / 1440), h = Math.floor((m % 1440) / 60), r = m % 60;
            return [d ? `${d} Tg.` : '', h ? `${h} Std.` : '', r ? `${r} Min.` : ''].filter(Boolean).join(' ');
        },
        get summary() {
            const s = this.startDt;
            if (!s) return '';
            let t = `${fmtDay(s)}, ${this.time} Uhr`;
            const e = this.endDt;
            if (e) t += ` bis ${e.toDateString() !== s.toDateString() ? fmtDay(e) + ', ' : ''}${hm(e)} Uhr`;
            if (this.durationText) t += ` (${this.durationText})`;
            const d = this.doorsDt;
            if (d) t += ` · Einlass ab ${hm(d)} Uhr`;
            return t;
        },
        get warnings() {
            const w = [];
            const s = this.startDt, e = this.endDt, d = this.doorsDt;
            if (s && s < new Date()) w.push('Der Beginn liegt in der Vergangenheit.');
            if (e && s && e <= s) w.push('Das Ende muss nach dem Beginn liegen.');
            if (d && s && d > s) w.push('Der Einlass muss vor dem Beginn sein.');
            if (e && s && !this.multi && e.toDateString() !== s.toDateString()) w.push('Das Event endet erst am Folgetag.');
            if (e && s && e - s > 14 * 86400000) w.push('Das Event dauert länger als zwei Wochen. Stimmt das Enddatum?');
            return w;
        },
        get hardError() { return this.warnings.some((x) => x.startsWith('Das Ende muss') || x.startsWith('Der Einlass')); },
    };
}
