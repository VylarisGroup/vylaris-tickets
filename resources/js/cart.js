// Gemeinsame Logik für Online-Shop und Abendkasse: Ticketauswahl, Platzwahl und Total.
export default function cart(config) {
    return {
        types: config.types || {},
        currency: config.currency || 'CHF',
        seating: config.seating || null,
        items: Object.fromEntries(Object.keys(config.types || {}).map((id) => [id, Number((config.old?.items || {})[id] || 0)])),
        selected: [...(config.old?.seats || [])],
        max: config.maxSeats || 10,

        get sections() { return this.seating ? this.seating.sections : []; },
        get seats() { return this.seating ? this.seating.seats : []; },
        get viewBox() {
            if (!this.seats.length) return '0 0 100 100';
            const xs = this.seats.map((s) => s.x), ys = this.seats.map((s) => s.y);
            const pad = 30;
            const x0 = Math.min(...xs) - pad, y0 = Math.min(...ys) - pad;
            return `${x0} ${y0} ${Math.max(...xs) + pad - x0} ${Math.max(...ys) + pad - y0}`;
        },
        byId(id) { return this.seats.find((s) => s.id === id) || { label: '?' }; },
        section(seat) { return this.sections.find((s) => s.id === seat.section); },
        sellable(seat) { return !!this.section(seat)?.sellable; },
        color(seat) { return this.section(seat)?.color || '#94a3b8'; },
        isSelected(seat) { return this.selected.includes(seat.id); },
        toggle(seat) {
            if (seat.taken || !this.sellable(seat)) return;
            if (this.isSelected(seat)) {
                this.selected = this.selected.filter((id) => id !== seat.id);
            } else if (this.selected.length < this.max) {
                this.selected = [...this.selected, seat.id];
            }
        },

        get count() {
            return Object.values(this.items).reduce((a, b) => a + Number(b || 0), 0) + this.selected.length;
        },
        get total() {
            const general = Object.entries(this.items).reduce((sum, [id, q]) => sum + Number(q || 0) * (this.types[id]?.price_cents || 0), 0);
            const seated = this.selected.reduce((sum, id) => sum + (this.section(this.byId(id))?.price_cents || 0), 0);
            return general + seated;
        },
        format(cents) {
            if (cents === 0) return 'Gratis';
            return this.currency + ' ' + (cents / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, "'");
        },
    };
}
