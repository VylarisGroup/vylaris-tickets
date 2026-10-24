// Saalplan-Editor: Ebenen (Stockwerke/Räume) mit Hallenplan als Hintergrund, Sitze und Tische als Marker setzen,
// Reihen ziehen, verschieben, Bereichen zuweisen, sperren und speichern.
const COLORS = ['#1867f8', '#0e9f6e', '#d97706', '#9333ea', '#e11d48', '#0891b2', '#65a30d', '#c2410c'];
const SNAP = 5;
const SPACING = 30;
const CANVAS_W = 1000;
const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const csrf = () => document.querySelector('meta[name=csrf-token]').content;

export default function seatEditor(state) {
    let uid = 0;
    let pdfDoc = null; // außerhalb des reaktiven Zustands: pdf.js verträgt keinen Proxy
    const key = () => `n${++uid}`;

    const fromServer = (st) => {
        const sections = st.sections.map((s) => ({ key: key(), id: s.id, name: s.name, ticket_type_id: s.ticket_type_id }));
        const secIndex = Object.fromEntries(sections.map((s, i) => [s.id, i]));
        const firstFloor = st.floors[0]?.id ?? null;
        const seats = st.seats.map((s) => ({
            key: key(), id: s.id, section: secIndex[s.section_id] ?? 0, floor: s.floor_id ?? firstFloor, label: s.label, row_label: s.row_label || '',
            number: s.number || 0, x: s.x, y: s.y, is_blocked: !!s.is_blocked, sold: !!s.sold,
            kind: s.kind || 'seat', capacity: s.capacity || 1, table_mode: s.table_mode || 'whole',
        }));
        return { sections, seats, floors: st.floors.map((f) => ({ ...f })) };
    };

    const initial = fromServer(state);
    if (!initial.sections.length && state.ticketTypes.length) {
        initial.sections.push({ key: key(), id: null, name: 'Parkett', ticket_type_id: state.ticketTypes[0].id });
    }

    return {
        ticketTypes: state.ticketTypes,
        saveUrl: state.saveUrl,
        floorUrl: state.floorUrl,
        floors: initial.floors,
        activeFloorId: initial.floors[0]?.id ?? null,
        planOpacity: 0.85,
        tableCapacity: 8,
        tableMode: 'whole',
        planBusy: false,
        pdf: null,
        pdfPage: 1,
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
        get floor() { return this.floors.find((f) => f.id === this.activeFloorId) || this.floors[0] || null; },
        get floorSeats() { return this.seats.filter((s) => s.floor === this.floor?.id); },
        floorCount(f) { return this.seats.filter((s) => s.floor === f.id).length; },
        get bounds() {
            const list = this.floorSeats;
            const xs = list.map((s) => s.x), ys = list.map((s) => s.y);
            return { w: Math.max(CANVAS_W, (xs.length ? Math.max(...xs) : 0) + 120), h: Math.max(this.floor?.h || 640, (ys.length ? Math.max(...ys) : 0) + 120) };
        },
        get viewBox() { return `0 0 ${this.bounds.w} ${this.bounds.h}`; },
        color(seat) { return COLORS[seat.section % COLORS.length]; },
        sectionColor(i) { return COLORS[i % COLORS.length]; },
        isSelected(seat) { return this.selected.includes(seat.key); },
        get selectedSeats() { return this.seats.filter((s) => this.selected.includes(s.key)); },
        get selectedTables() { return this.selectedSeats.filter((s) => s.kind === 'table'); },
        get single() { return this.selectedSeats.length === 1 ? this.selectedSeats[0] : null; },
        countIn(i) { return this.seats.filter((s) => s.section === i).length; },

        // `<template>` funktioniert innerhalb von <svg> nicht (SVG-Namensraum), deshalb wird die Markierung als Text erzeugt.
        get markup() {
            return this.floorSeats.map((seat) => {
                const sel = this.isSelected(seat);
                const fill = seat.is_blocked ? '#cbd5e1' : this.color(seat);
                const stroke = sel ? '#0f172a' : (seat.sold ? '#f59e0b' : 'white');
                const title = `${seat.label}${seat.kind === 'table' ? ` (${seat.capacity} Plätze, ${seat.table_mode === 'whole' ? 'nur ganzer Tisch' : 'einzelne Plätze'})` : ''}${seat.is_blocked ? ' (gesperrt)' : ''}${seat.sold ? ' (hat Bestellungen)' : ''}`;
                if (seat.kind === 'table') {
                    return `<g data-k="${seat.key}" style="cursor:pointer"><rect x="${seat.x - 20}" y="${seat.y - 20}" width="40" height="40" rx="12" fill="${fill}" stroke="${stroke}" stroke-width="${sel || seat.sold ? 3 : 2}"/>`
                        + `<text x="${seat.x}" y="${seat.y + 1}" text-anchor="middle" font-size="13" font-weight="700" fill="white" style="pointer-events:none">${esc(seat.capacity)}</text>`
                        + `<text x="${seat.x}" y="${seat.y + 13}" text-anchor="middle" font-size="8" fill="white" style="pointer-events:none">${seat.table_mode === 'whole' ? 'ganz' : 'einzeln'}</text>`
                        + `<text x="${seat.x}" y="${seat.y + 34}" text-anchor="middle" font-size="11" fill="#0f172a" stroke="white" stroke-width="3" paint-order="stroke" style="pointer-events:none">${esc(seat.label)}</text><title>${esc(title)}</title></g>`;
                }
                return `<g data-k="${seat.key}" style="cursor:pointer"><circle cx="${seat.x}" cy="${seat.y}" r="11" fill="${fill}" stroke="${stroke}" stroke-width="${sel || seat.sold ? 3 : 1.5}"/>`
                    + `<text x="${seat.x}" y="${seat.y + 3}" text-anchor="middle" font-size="8" fill="white" style="pointer-events:none">${esc(seat.number || '')}</text><title>${esc(title)}</title></g>`;
            }).join('');
        },
        svgDown(evt) {
            const key = evt.target.closest?.('[data-k]')?.dataset.k;
            const seat = key ? this.seats.find((s) => s.key === key) : null;
            if (seat) { this.seatDown(evt, seat); } else { this.canvasDown(evt); }
        },

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
            const seat = { key: key(), id: null, section: this.activeSection, floor: this.floor.id, label, row_label: row, number, x: this.snap(x), y: this.snap(y), is_blocked: false, sold: false, kind: 'seat', capacity: 1, table_mode: 'whole' };
            this.seats.push(seat);
            this.dirty = true;
            return seat;
        },
        addTable(x, y) {
            const used = new Set(this.seats.map((s) => s.label));
            let n = this.seats.filter((s) => s.kind === 'table').length + 1;
            while (used.has(`Tisch ${n}`)) n++;
            const seat = { key: key(), id: null, section: this.activeSection, floor: this.floor.id, label: `Tisch ${n}`, row_label: '', number: n, x: this.snap(x), y: this.snap(y), is_blocked: false, sold: false, kind: 'table', capacity: Number(this.tableCapacity) || 4, table_mode: this.tableMode };
            this.seats.push(seat);
            this.selected = [seat.key];
            this.dirty = true;
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
                const seat = { key: key(), id: null, section: this.activeSection, floor: this.floor.id, label, row_label: this.rowName, number, x: this.snap(a.x + dx * t), y: this.snap(a.y + dy * t), is_blocked: false, sold: false, kind: 'seat', capacity: 1, table_mode: 'whole' };
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
            if (this.tool === 'table') { this.addTable(p.x, p.y); return; }
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
                const inside = this.floorSeats.filter((s) => s.x >= minX && s.x <= maxX && s.y >= minY && s.y <= maxY).map((s) => s.key);
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
        assignFloor(id) {
            if (!id) return;
            this.selectedSeats.forEach((s) => { s.floor = Number(id); });
            this.activeFloorId = Number(id);
            this.dirty = true;
        },
        setCapacity(v) {
            const n = Math.max(1, Math.min(200, Number(v) || 1));
            this.selectedTables.forEach((s) => { s.capacity = n; });
            this.dirty = true;
        },
        setTableMode(m) { this.selectedTables.forEach((s) => { s.table_mode = m; }); this.dirty = true; },
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

        // --- Ebenen und Hallenplan ---------------------------------------------------------------
        async api(url, method, body, isForm = false) {
            const res = await fetch(url, {
                method, body: isForm ? body : (body ? JSON.stringify(body) : undefined),
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), ...(isForm || !body ? {} : { 'Content-Type': 'application/json' }) },
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error((json.errors ? Object.values(json.errors)[0][0] : json.message) || 'Das hat nicht geklappt.');
            }
            return json;
        },
        async addFloor() {
            const name = prompt('Name der Ebene (z. B. «1. Obergeschoss» oder «Festsaal»)', `Ebene ${this.floors.length + 1}`);
            if (!name) return;
            this.error = '';
            try {
                const { floor } = await this.api(this.floorUrl, 'POST', { name });
                this.floors.push(floor);
                this.activeFloorId = floor.id;
                this.selected = [];
                this.pdf = null;
            } catch (e) { this.error = e.message; }
        },
        async renameFloor(f) {
            this.error = '';
            try { await this.api(f.url, 'PUT', { name: f.name }); } catch (e) { this.error = e.message; }
        },
        async removeFloor(f) {
            if (this.floors.length <= 1) { this.error = 'Mindestens eine Ebene ist nötig.'; return; }
            if (this.seats.some((s) => s.floor === f.id && s.sold)) { this.error = 'Für Plätze dieser Ebene gibt es schon Bestellungen.'; return; }
            const n = this.floorCount(f);
            if (!confirm(`Ebene «${f.name}»${n ? ` samt ${n} Plätzen/Tischen` : ''} löschen?`)) return;
            this.error = '';
            try {
                await this.api(f.url, 'DELETE');
                this.seats = this.seats.filter((s) => s.floor !== f.id);
                this.floors = this.floors.filter((x) => x.id !== f.id);
                this.activeFloorId = this.floors[0].id;
                this.selected = [];
            } catch (e) { this.error = e.message; }
        },
        async uploadBlob(blob, name) {
            const f = this.floor;
            const fd = new FormData();
            fd.append('plan', blob, name);
            const { floor } = await this.api(f.uploadUrl, 'POST', fd, true);
            Object.assign(f, floor);
        },
        async pickPlan(evt) {
            const file = evt.target.files[0];
            evt.target.value = '';
            if (!file) return;
            this.error = ''; this.pdf = null; this.planBusy = true;
            try {
                if (file.type === 'application/pdf' || /\.pdf$/i.test(file.name)) {
                    await this.openPdf(file);
                } else {
                    await this.uploadBlob(file, file.name);
                }
            } catch (e) {
                this.error = e.message || 'Der Plan konnte nicht geladen werden.';
            } finally { this.planBusy = false; }
        },
        async openPdf(file) {
            // PDF wird im Browser gerendert und als Bild hochgeladen (kein Server-Programm nötig).
            const pdfjs = await import('pdfjs-dist/build/pdf.min.mjs');
            const worker = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
            pdfjs.GlobalWorkerOptions.workerSrc = worker;
            pdfDoc = await pdfjs.getDocument({ data: await file.arrayBuffer() }).promise;
            this.pdf = { pages: pdfDoc.numPages, name: file.name.replace(/\.pdf$/i, '') };
            this.pdfPage = 1;
            await this.usePdfPage();
        },
        async usePdfPage() {
            if (!this.pdf) return;
            this.planBusy = true; this.error = '';
            try {
                const page = await pdfDoc.getPage(Math.min(Math.max(1, Number(this.pdfPage) || 1), this.pdf.pages));
                const base = page.getViewport({ scale: 1 });
                const viewport = page.getViewport({ scale: Math.min(4, 2400 / base.width) });
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(viewport.width); canvas.height = Math.round(viewport.height);
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
                await page.render({ canvasContext: ctx, viewport }).promise;
                const blob = await new Promise((res) => canvas.toBlob(res, 'image/jpeg', 0.92));
                await this.uploadBlob(blob, `${this.pdf.name}-${this.pdfPage}.jpg`);
            } catch (e) {
                this.error = e.message || 'Das PDF konnte nicht gelesen werden.';
            } finally { this.planBusy = false; }
        },
        async removePlan() {
            if (!this.floor?.plan || !confirm('Hallenplan dieser Ebene entfernen? Die Marker bleiben erhalten.')) return;
            try {
                const { floor } = await this.api(this.floor.uploadUrl, 'DELETE');
                Object.assign(this.floor, floor); this.pdf = null;
            } catch (e) { this.error = e.message; }
        },

        async save() {
            this.saving = true; this.error = ''; this.message = '';
            try {
                const res = await fetch(this.saveUrl, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({
                        sections: this.sections.map((s) => ({ id: s.id, name: s.name, ticket_type_id: s.ticket_type_id })),
                        seats: this.seats.map((s) => ({ id: s.id, section: s.section, floor_id: s.floor, label: s.label, row_label: s.row_label || null, number: s.number, x: s.x, y: s.y, is_blocked: s.is_blocked, kind: s.kind, capacity: s.capacity, table_mode: s.table_mode })),
                    }),
                });
                const json = await res.json();
                if (!res.ok) {
                    const first = json.errors ? Object.values(json.errors)[0][0] : json.message;
                    throw new Error(first || 'Speichern fehlgeschlagen.');
                }
                const fresh = fromServer(json.state);
                this.sections = fresh.sections; this.seats = fresh.seats; this.floors = fresh.floors; this.selected = [];
                if (!this.floors.some((f) => f.id === this.activeFloorId)) this.activeFloorId = this.floors[0]?.id ?? null;
                this.dirty = false;
                const tables = this.seats.filter((s) => s.kind === 'table').length;
                this.message = `Gespeichert: ${this.seats.length - tables} Plätze${tables ? `, ${tables} Tische` : ''}.`;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.saving = false;
            }
        },
    };
}
