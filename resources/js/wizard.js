// Assistent «Neues Event»: Schritte, Ticket-Vorlagen und Prüfung pro Schritt. Gesendet wird einmal am Ende.
const blank = () => ({ name: '', description: '', price: '', quota: '', max_per_order: 10, sales_start_at: '', sales_end_at: '', more: false });

const PRESETS = {
    free: () => [{ ...blank(), name: 'Eintritt', price: '0' }],
    standard: () => [{ ...blank(), name: 'Normal', price: '25.00' }, { ...blank(), name: 'Ermässigt', price: '15.00', description: 'Schüler, Studierende, AHV/IV' }],
    early: () => [
        { ...blank(), name: 'Early Bird', price: '18.00', quota: 50 },
        { ...blank(), name: 'Regulär', price: '25.00' },
        { ...blank(), name: 'VIP', price: '60.00', quota: 20, description: 'Bester Zugang, inkl. Begrüssungsgetränk' },
    ],
};

export default (cfg) => ({
    step: cfg.step || 1,
    labels: ['Grundlagen', 'Ort & Zeit', 'Tickets', 'Verkauf', 'Fertig'],
    f: { title: '', type: 'onsite', category: '', starts_at: '', venue_name: '', city: '', currency: 'CHF', publish: '0', door_sales_enabled: true, ...cfg.old },
    tickets: (cfg.tickets && cfg.tickets.length ? cfg.tickets : PRESETS.standard()).map((t) => ({ ...blank(), ...t })),
    error: '',

    init() {
        this.f.door_sales_enabled = [true, '1', 1, 'on'].includes(this.f.door_sales_enabled);
    },

    get hasVenue() { return this.f.type !== 'digital'; },
    get isOnline() { return this.f.type !== 'onsite'; },
    get progress() { return Math.round(((this.step - 1) / (this.labels.length - 1)) * 100); },
    get typeLabel() { return { onsite: 'Vor Ort', digital: 'Digital', hybrid: 'Hybrid' }[this.f.type]; },
    get categoryLabel() { return cfg.categories[this.f.category] || '–'; },
    get startLabel() {
        if (!this.f.starts_at) return '–';
        const d = new Date(this.f.starts_at);
        return isNaN(d) ? '–' : d.toLocaleString('de-CH', { weekday: 'short', day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) + ' Uhr';
    },
    get capacity() {
        if (this.tickets.some((t) => !t.quota)) return null;
        return this.tickets.reduce((n, t) => n + Number(t.quota || 0), 0);
    },
    get potential() {
        if (this.capacity === null) return null;
        return this.tickets.reduce((n, t) => n + Number(String(t.price || 0).replace(',', '.')) * Number(t.quota || 0), 0);
    },
    money(v) {
        return this.f.currency + ' ' + Number(v).toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/’/g, "'");
    },
    priceLabel(t) {
        const p = Number(String(t.price || 0).replace(',', '.'));
        return p === 0 ? 'Gratis' : this.money(p);
    },

    preset(name) { this.tickets = PRESETS[name](); },
    addTicket() { if (this.tickets.length < 20) this.tickets.push(blank()); },
    removeTicket(i) { if (this.tickets.length > 1) this.tickets.splice(i, 1); },
    moveTicket(i, d) {
        const j = i + d;
        if (j < 0 || j >= this.tickets.length) return;
        [this.tickets[i], this.tickets[j]] = [this.tickets[j], this.tickets[i]];
    },

    fieldsOf(n) {
        const el = this.$refs['s' + n];

        return el ? [...el.querySelectorAll('input, select, textarea')].filter((x) => x.willValidate) : [];
    },
    valid(n) {
        this.error = '';
        // Pflichtfelder sind nur gesetzt, wenn sie zur gewählten Art passen (:required in der Ansicht)
        const bad = this.fieldsOf(n).find((x) => !x.checkValidity());
        if (bad) { bad.reportValidity(); return false; }
        if (n === 2 && this.f.starts_at) {
            const ends = this.$refs.s2.querySelector('[name=ends_at]')?.value;
            if (ends && new Date(ends) <= new Date(this.f.starts_at)) { this.error = 'Das Ende muss nach dem Beginn liegen.'; return false; }
        }
        return true;
    },
    next() { if (this.valid(this.step)) this.go(this.step + 1); },
    back() { this.go(this.step - 1); },
    go(n) {
        this.step = Math.min(Math.max(n, 1), this.labels.length);
        this.$nextTick(() => this.$root.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    },
    submit(e) {
        for (let n = 1; n <= 4; n++) {
            if (!this.fieldsOf(n).every((x) => x.checkValidity())) {
                e.preventDefault();
                this.step = n;
                this.$nextTick(() => this.fieldsOf(n).find((x) => !x.checkValidity())?.reportValidity());
                return;
            }
        }
    },
});
