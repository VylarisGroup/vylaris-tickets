// Gemeinsame Logik für Online-Shop und Abendkasse: Ticketauswahl, Platzwahl und Total.
export default function cart(config) {
    return {
        types: config.types || {},
        currency: config.currency || 'CHF',
        seating: config.seating || null,
        items: Object.fromEntries(Object.keys(config.types || {}).map((id) => [id, Number((config.old?.items || {})[id] || 0)])),
        // Platzwahl: Platz-ID -> Anzahl (Einzelplatz 1, ganzer Tisch = Plätze, Tisch mit Einzelplätzen = gewählte Anzahl)
        sel: Object.fromEntries((config.old?.seats || []).map((v) => String(v).split('x')).map(([id, q]) => [Number(id), q ? Number(q) : null])),
        floorId: null,
        picker: null,
        zoom: 1,
        max: config.maxSeats || 10,

        // Angaben pro Ticket (personalisierte Tickets). Schlüssel «t12-0» / «s5-1» wie auf dem Server (Ticketart bzw. Platz + Laufnummer).
        attendees: config.attendees || null,
        holders: config.old?.holders && !Array.isArray(config.old.holders) ? JSON.parse(JSON.stringify(config.old.holders)) : {},
        today: new Date().toISOString().slice(0, 10),
        step: 0, // aktuell angezeigte Person (eine nach der anderen ausfüllen)

        get attendeeFields() { return this.attendees?.fields || []; },
        get slots() {
            if (!this.attendees) return [];
            const out = [];
            Object.keys(this.types).forEach((id) => {
                if (this.types[id].seated) return;
                for (let i = 0; i < Number(this.items[id] || 0); i++) out.push({ key: `t${id}-${i}`, label: this.types[id].name });
            });
            this.selectedSeats.forEach((seat) => {
                const n = this.qtyOf(seat);
                for (let i = 0; i < n; i++) out.push({ key: `s${seat.id}-${i}`, label: this.isTable(seat) ? `${seat.label}, Platz ${i + 1}` : seat.label });
            });
            return out;
        },
        // Über den Proxy lesen (nicht das Ergebnis von ??= zurückgeben), sonst wäre das Objekt nicht reaktiv.
        h(key) { if (!this.holders[key]) this.holders[key] = {}; return this.holders[key]; },
        fillFromBuyer(slot) {
            const form = this.$root;
            const h = this.h(slot.key);
            h.name = form.querySelector('[name=name]')?.value || h.name || '';
            if (this.attendeeFields.some((f) => f.key === 'email')) h.email = form.querySelector('[name=email]')?.value || h.email || '';
        },

        get stepIdx() { return Math.min(this.step, Math.max(this.slots.length - 1, 0)); },
        stepFields(n) { return [...this.$root.querySelectorAll(`[data-slot="${n}"] input`)]; },
        stepValid(n) {
            const bad = this.stepFields(n).find((el) => !el.checkValidity());
            if (bad) { this.step = n; this.$nextTick(() => bad.reportValidity()); }
            return !bad;
        },
        stepDone(n) { return this.stepFields(n).length > 0 && this.stepFields(n).every((el) => el.checkValidity()); },
        goTo(n) { if (n < 0 || n >= this.slots.length) return; this.step = n; this.$nextTick(() => this.$root.querySelector(`[data-slot="${n}"] input`)?.focus({ preventScroll: true })); },
        nextStep() { if (this.stepValid(this.stepIdx)) this.goTo(this.stepIdx + 1); },
        // Beim Absenden die erste unvollständige Person anzeigen, statt dass der Browser ein verstecktes Feld nicht fokussieren kann.
        checkAll(e) {
            for (let n = 0; n < this.slots.length; n++) {
                if (!this.stepValid(n)) { e.preventDefault(); return; }
            }
        },

        init() {
            this.floorId = this.floors[0]?.id ?? null;
            // Alte Wahl (nach einem Fehler) wiederherstellen: Tische mit Einzelplätzen brauchen mindestens 1 Platz.
            Object.keys(this.sel).forEach((id) => { if (!this.byId(Number(id)).id) delete this.sel[id]; });
        },

        get sections() { return this.seating ? this.seating.sections : []; },
        get floors() { return this.seating?.floors || []; },
        get seats() { return this.seating ? this.seating.seats : []; },
        get floor() { return this.floors.find((f) => f.id === this.floorId) || null; },
        get floorSeats() { return this.floor ? this.seats.filter((s) => s.floor === this.floor.id) : this.seats; },
        get viewBox() {
            if (this.floor?.plan) {
                const maxY = Math.max(this.floor.h, ...this.floorSeats.map((s) => s.y + 40));
                return `0 0 ${this.floor.w} ${maxY}`;
            }
            const list = this.floorSeats;
            if (!list.length) return '0 0 100 100';
            const xs = list.map((s) => s.x), ys = list.map((s) => s.y);
            const pad = 34;
            const x0 = Math.min(...xs) - pad, y0 = Math.min(...ys) - pad;
            return `${x0} ${y0} ${Math.max(...xs) + pad - x0} ${Math.max(...ys) + pad + 10 - y0}`;
        },
        // `<template>` funktioniert innerhalb von <svg> nicht (SVG-Namensraum), deshalb wird die Markierung als Text erzeugt.
        get markup() {
            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            return this.floorSeats.map((seat) => {
                const off = seat.taken || !this.sellable(seat);
                const sel = this.isSelected(seat);
                const fill = off ? '#cbd5e1' : (sel ? '#0f172a' : this.color(seat));
                const cursor = off ? 'not-allowed' : 'pointer';
                if (this.isTable(seat)) {
                    const free = seat.mode === 'seats' && !seat.taken ? seat.free : seat.capacity;
                    const tip = `${seat.label} · ${seat.taken ? 'vergeben' : (seat.mode === 'seats' ? `${seat.free} von ${seat.capacity} Plätzen frei` : `ganzer Tisch, ${seat.capacity} Plätze`)}`;
                    return `<g data-id="${seat.id}" style="cursor:${cursor}"><rect x="${seat.x - 20}" y="${seat.y - 20}" width="40" height="40" rx="12" fill="${fill}" stroke="${sel ? '#0f172a' : 'white'}" stroke-width="${sel ? 3 : 2}"/>`
                        + `<text x="${seat.x}" y="${seat.y + 5}" text-anchor="middle" font-size="14" font-weight="700" fill="white" style="pointer-events:none">${free}</text>`
                        + `<text x="${seat.x}" y="${seat.y + 34}" text-anchor="middle" font-size="11" fill="#0f172a" stroke="white" stroke-width="3" paint-order="stroke" style="pointer-events:none">${esc(seat.label)}</text><title>${esc(tip)}</title></g>`;
                }
                return `<g data-id="${seat.id}" style="cursor:${cursor}"><circle cx="${seat.x}" cy="${seat.y}" r="11" fill="${fill}" stroke="${sel ? '#0f172a' : 'white'}" stroke-width="${sel ? 3 : 1.5}"/><title>${esc(seat.label + (seat.taken ? ' (vergeben)' : ''))}</title></g>`;
            }).join('');
        },
        svgClick(evt) {
            const id = Number(evt.target.closest?.('[data-id]')?.dataset.id);
            const seat = id ? this.seats.find((s) => s.id === id) : null;
            if (seat) this.toggle(seat);
        },
        floorSelected(f) { return this.seats.filter((s) => s.floor === f.id && this.isSelected(s)).reduce((n, s) => n + this.qtyOf(s), 0); },
        byId(id) { return this.seats.find((s) => s.id === id) || { label: '?' }; },
        section(seat) { return this.sections.find((s) => s.id === seat.section); },
        sellable(seat) { return !!this.section(seat)?.sellable; },
        color(seat) { return this.section(seat)?.color || '#94a3b8'; },
        isTable(seat) { return seat.kind === 'table'; },
        isSelected(seat) { return Object.prototype.hasOwnProperty.call(this.sel, seat.id); },
        qtyOf(seat) {
            if (!this.isSelected(seat)) return 0;
            if (!this.isTable(seat)) return 1;
            return seat.mode === 'whole' ? seat.capacity : Math.min(this.sel[seat.id] || 1, seat.free);
        },
        get selectedSeats() { return this.seats.filter((s) => this.isSelected(s)); },
        get selectedIds() { return Object.keys(this.sel).map(Number); },
        // Formularwerte: «12» oder «12x3» (Tisch mit Einzelplätzen)
        get seatParams() {
            return this.selectedSeats.map((s) => (this.isTable(s) && s.mode === 'seats' ? `${s.id}x${this.qtyOf(s)}` : String(s.id)));
        },
        toggle(seat) {
            if (seat.taken || !this.sellable(seat)) return;
            if (this.isTable(seat) && seat.mode === 'seats') {
                this.picker = seat;
                if (!this.isSelected(seat) && this.selectedIds.length >= this.max) { this.picker = null; }
                return;
            }
            this.picker = null;
            if (this.isSelected(seat)) {
                delete this.sel[seat.id];
                this.sel = { ...this.sel };
            } else if (this.selectedIds.length < this.max) {
                this.sel = { ...this.sel, [seat.id]: null };
            }
        },
        setQty(seat, qty) {
            const n = Math.max(0, Math.min(seat.free, Number(qty) || 0));
            const next = { ...this.sel };
            if (n === 0) { delete next[seat.id]; } else { next[seat.id] = n; }
            this.sel = next;
        },
        remove(seat) { const next = { ...this.sel }; delete next[seat.id]; this.sel = next; if (this.picker?.id === seat.id) this.picker = null; },

        get count() {
            return Object.values(this.items).reduce((a, b) => a + Number(b || 0), 0) + this.selectedSeats.reduce((n, s) => n + this.qtyOf(s), 0);
        },
        get total() {
            const general = Object.entries(this.items).reduce((sum, [id, q]) => sum + Number(q || 0) * (this.types[id]?.price_cents || 0), 0);
            const seated = this.selectedSeats.reduce((sum, s) => sum + this.qtyOf(s) * (this.section(s)?.price_cents || 0), 0);
            return general + seated;
        },
        format(cents) {
            if (cents === 0) return 'Gratis';
            return this.currency + ' ' + (cents / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, "'");
        },
    };
}
