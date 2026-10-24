// Saalplan-Editor: Plätze setzen, Reihen ziehen, verschieben, Bereichen zuweisen, sperren und speichern.
const COLORS = ['#1867f8', '#0e9f6e', '#d97706', '#9333ea', '#e11d48', '#0891b2', '#65a30d', '#c2410c'];
const SNAP = 5;
const SPACING = 30;

export default function seatEditor(state) {
    let uid = 0;
    const key = () => `n${++uid}`;

    const fromServer = (st) => {
        const sections = st.sections.map((s) => ({ key: key(), id: s.id, name: s.name, ticket_type_id: s.ticket_type_id }));
        const secIndex = Object.fromEntries(sections.map((s, i) => [s.id, i]));
        const seats = st.seats.map((s) => ({
            key: key(), id: s.id, section: secIndex[s.section_id] ?? 0, label: s.label, row_label: s.row_label || '',
            number: s.number || 0, x: s.x, y: s.y, is_blocked: !!s.is_blocked, sold: !!s.sold,
        }));
        return { sections, seats };
    };

    const initial = fromServer(state);
    if (!initial.sections.length && state.ticketTypes.length) {
        initial.sections.push({ key: key(), id: null, name: 'Parkett', ticket_type_id: state.ticketTypes[0].id });
    }

    return {
        ticketTypes: state.ticketTypes,
        saveUrl: state.saveUrl,
        sections: initial.sections,
        seats: initial.seats,
        selected: [],
        tool: 'select',
        activeSection: 0,
        rowName: 'A',
        rowStart: null,
        drag: null,
        band: null,
        zoom: 1,
        dirty: false,
        saving: false,
        message: '',
        error: '',

        init() {
            window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
        },

        // --- Darstellung ---------------------------------------------------------------------
        get bounds() {
            const xs = this.seats.map((s) => s.x), ys = this.seats.map((s) => s.y);
            return { w: Math.max(1000, (xs.length ? Math.max(...xs) : 0) + 120), h: Math.max(640, (ys.length ? Math.max(...ys) : 0) + 120) };
        },
        get viewBox() { return `0 0 ${this.bounds.w} ${this.bounds.h}`; },
        color(seat) { return COLORS[seat.section % COLORS.length]; },
        sectionColor(i) { return COLORS[i % COLORS.length]; },
        isSelected(seat) { return this.selected.includes(seat.key); },
        get selectedSeats() { return this.seats.filter((s) => this.selected.includes(s.key)); },
        get single() { return this.selectedSeats.length === 1 ? this.selectedSeats[0] : null; },
        countIn(i) { return this.seats.filter((s) => s.section === i).length; },

        // --- Koordinaten ---------------------------------------------------------------------
        point(evt) {
            const svg = this.$refs.canvas;
            const pt = svg.createSVGPoint();
            pt.x = evt.clientX; pt.y = evt.clientY;
            const p = pt.matrixTransform(svg.getScreenCTM().inverse());
            return { x: p.x, y: p.y };
        },
        snap(v) { return Math.max(0, Math.round(v / SNAP) * SNAP); },

        // --- Werkzeuge ------------------------------------------------------------------------
        nextLabelFor(row, taken = []) {
            const used = new Set([...this.seats.map((s) => s.label), ...taken]);
            let n = this.seats.filter((s) => s.row_label === row).length + taken.length + 1;
            while (used.has(`${row}${n}`)) n++;
            return { label: `${row}${n}`, number: n };
        },
        addSeat(x, y, row = this.rowName, extra = []) {
            const { label, number } = this.nextLabelFor(row, extra);
            const seat = { key: key(), id: null, section: this.activeSection, label, row_label: row, number, x: this.snap(x), y: this.snap(y), is_blocked: false, sold: false };
            this.seats.push(seat);
            this.dirty = true;
            return seat;
        },
        nextRowName() {
            const name = this.rowName;
            if (/^\d+$/.test(name)) return String(Number(name) + 1);
            let n = 0;
            for (const ch of name.toUpperCase()) n = n * 26 + (ch.charCodeAt(0) - 64);
            n++;
            let out = '';
            while (n > 0) { n--; out = String.fromCharCode(65 + (n % 26)) + out; n = Math.floor(n / 26); }
            return out;
        },
        addRow(a, b) {
            const dx = b.x - a.x, dy = b.y - a.y;
            const dist = Math.hypot(dx, dy);
            const count = Math.max(2, Math.floor(dist / SPACING) + 1);
            const created = [];
            for (let i = 0; i < count; i++) {
                const t = count === 1 ? 0 : i / (count - 1);
                const { label, number } = this.nextLabelFor(this.rowName, created.map((c) => c.label));
                const seat = { key: key(), id: null, section: this.activeSection, label, row_label: this.rowName, number, x: this.snap(a.x + dx * t), y: this.snap(a.y + dy * t), is_blocked: false, sold: false };
                this.seats.push(seat);
                created.push(seat);
            }
            this.dirty = true;
            this.rowName = this.nextRowName();
        },

        // --- Maus / Touch ----------------------------------------------------------------------
        canvasDown(evt) {
            const p = this.point(evt);
            if (this.tool === 'seat') { this.addSeat(p.x, p.y); return; }
            if (this.tool === 'row') {
                if (!this.rowStart) { this.rowStart = p; } else { this.addRow(this.rowStart, p); this.rowStart = null; }
                return;
            }
            if (!evt.shiftKey) this.selected = [];
            this.band = { x0: p.x, y0: p.y, x1: p.x, y1: p.y, additive: evt.shiftKey };
        },
        seatDown(evt, seat) {
            if (this.tool !== 'select') return;
            evt.stopPropagation();
            if (evt.shiftKey) {
                this.selected = this.isSelected(seat) ? this.selected.filter((k) => k !== seat.key) : [...this.selected, seat.key];
            } else if (!this.isSelected(seat)) {
                this.selected = [seat.key];
            }
            const p = this.point(evt);
            this.drag = { last: p, moved: false };
        },
        move(evt) {
            const p = this.point(evt);
            if (this.drag) {
                const dx = p.x - this.drag.last.x, dy = p.y - this.drag.last.y;
                if (Math.abs(dx) >= SNAP || Math.abs(dy) >= SNAP) {
                    const sx = Math.round(dx / SNAP) * SNAP, sy = Math.round(dy / SNAP) * SNAP;
                    this.selectedSeats.forEach((s) => { s.x = Math.max(0, s.x + sx); s.y = Math.max(0, s.y + sy); });
                    this.drag.last = { x: this.drag.last.x + sx, y: this.drag.last.y + sy };
                    this.drag.moved = true;
                    this.dirty = true;
                }
            } else if (this.band) {
                this.band.x1 = p.x; this.band.y1 = p.y;
            }
        },
        up() {
            if (this.band) {
                const { x0, y0, x1, y1 } = this.band;
                const [minX, maxX] = [Math.min(x0, x1), Math.max(x0, x1)];
                const [minY, maxY] = [Math.min(y0, y1), Math.max(y0, y1)];
                const inside = this.seats.filter((s) => s.x >= minX && s.x <= maxX && s.y >= minY && s.y <= maxY).map((s) => s.key);
                this.selected = this.band.additive ? [...new Set([...this.selected, ...inside])] : inside;
                this.band = null;
            }
            this.drag = null;
        },
        get bandRect() {
            if (!this.band) return null;
            return { x: Math.min(this.band.x0, this.band.x1), y: Math.min(this.band.y0, this.band.y1), w: Math.abs(this.band.x1 - this.band.x0), h: Math.abs(this.band.y1 - this.band.y0) };
        },
        keydown(evt) {
            if (['INPUT', 'SELECT', 'TEXTAREA'].includes(evt.target.tagName)) return;
            if (evt.key === 'Delete' || evt.key === 'Backspace') { evt.preventDefault(); this.removeSelected(); }
            if (evt.key === 'Escape') { this.rowStart = null; this.selected = []; this.tool = 'select'; }
        },

        // --- Aktionen --------------------------------------------------------------------------
        removeSelected() {
            const blocked = this.selectedSeats.filter((s) => s.sold);
            this.seats = this.seats.filter((s) => !this.isSelected(s) || s.sold);
            this.selected = blocked.map((s) => s.key);
            this.dirty = true;
            this.error = blocked.length ? `${blocked.length} Platz/Plätze haben Bestellungen und bleiben erhalten. Sperre sie stattdessen.` : '';
        },
        assignSection(i) { this.selectedSeats.forEach((s) => { s.section = Number(i); }); this.dirty = true; },
        toggleBlocked() {
            const all = this.selectedSeats.every((s) => s.is_blocked);
            this.selectedSeats.forEach((s) => { s.is_blocked = !all; });
            this.dirty = true;
        },
        renameSingle(value) { if (this.single) { this.single.label = value; this.dirty = true; } },
        addSection() {
            this.sections.push({ key: key(), id: null, name: `Bereich ${this.sections.length + 1}`, ticket_type_id: this.ticketTypes[0]?.id });
            this.activeSection = this.sections.length - 1;
            this.dirty = true;
        },
        removeSection(i) {
            if (this.sections.length <= 1) { this.error = 'Mindestens ein Bereich ist nötig.'; return; }
            if (this.seats.some((s) => s.section === i && s.sold)) { this.error = 'Dieser Bereich hat Plätze mit Bestellungen.'; return; }
            if (this.countIn(i) && !confirm(`Bereich samt ${this.countIn(i)} Plätzen löschen?`)) return;
            this.seats = this.seats.filter((s) => s.section !== i).map((s) => ({ ...s, section: s.section > i ? s.section - 1 : s.section }));
            this.sections.splice(i, 1);
            this.activeSection = Math.min(this.activeSection, this.sections.length - 1);
            this.selected = [];
            this.dirty = true;
        },

        async save() {
            this.saving = true; this.error = ''; this.message = '';
            try {
                const res = await fetch(this.saveUrl, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({
                        sections: this.sections.map((s) => ({ id: s.id, name: s.name, ticket_type_id: s.ticket_type_id })),
                        seats: this.seats.map((s) => ({ id: s.id, section: s.section, label: s.label, row_label: s.row_label || null, number: s.number, x: s.x, y: s.y, is_blocked: s.is_blocked })),
                    }),
                });
                const json = await res.json();
                if (!res.ok) {
                    const first = json.errors ? Object.values(json.errors)[0][0] : json.message;
                    throw new Error(first || 'Speichern fehlgeschlagen.');
                }
                const fresh = fromServer(json.state);
                this.sections = fresh.sections; this.seats = fresh.seats; this.selected = [];
                this.dirty = false;
                this.message = `Gespeichert: ${this.seats.length} Plätze.`;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.saving = false;
            }
        },
    };
}
