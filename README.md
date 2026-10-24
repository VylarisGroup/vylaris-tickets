# Vylaris Tickets

Ticketing für Veranstaltungen vor Ort und digital: Vorverkauf, Abendkasse und Einlass in einem System.
Gebaut mit Laravel 13, Blade, Alpine.js, Tailwind CSS 4 und MySQL/MariaDB. Nur Deutsch.

## Mandantenstruktur (Plesk-Prinzip)

```
Plattform-Admin
└── Reseller (Whitelabel: eigene Domain/Subdomain, Name, Logo, Farbe, Limits)
    └── Veranstalter (Vereine, Clubs, Firmen)
        └── Team: Inhaber · Verwalter · Kasse · Einlass
```

- Der Reseller wird über den Hostnamen erkannt (`domain` oder `{kürzel}.{TICKETS_BASE_DOMAIN}`), ohne Treffer gilt das Vylaris-Design.
- Zahlungen gehen direkt an den Veranstalter (eigene Mollie-/Stripe-Schlüssel, verschlüsselt gespeichert).
- Veranstalter ohne Reseller hängen direkt an der Plattform.

## Installation (Entwicklung)

```sh
composer install
cp .env.example .env && php artisan key:generate
# DB-Zugang in .env eintragen, dann:
php artisan migrate
php artisan storage:link
npm install && npm run build
php artisan tickets:create-admin admin@example.ch "Admin"
php artisan serve
```

Tests: `php artisan test`

## Stand

- [x] Grundgerüst, Mandanten, Rollen, Whitelabel-Branding, Veranstalter-Einstellungen (MWST, Währung, Zahlungsschlüssel)
- [ ] Events (vor Ort und digital), Ticketarten, Gutscheine, Warteliste, Sitzplätze
- [ ] Shop, Checkout, Zahlungen (Mollie, Stripe, Überweisung, Bargeld), PDF-Ticket
- [ ] Abendkasse, QR-Einlass, Kassenabschluss
- [ ] Melden-Funktion, Update-System
