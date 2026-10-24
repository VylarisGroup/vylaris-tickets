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

## Funktionen

- **Vorverkauf**: öffentlicher Shop pro Veranstalter und Event, Ticketarten und Preise, Gutscheine (Prozent oder fixer Betrag), Warteliste bei Ausverkauf, MWST-Ausweis, CHF und EUR.
- **Zahlung direkt an den Veranstalter**: Mollie, Stripe (eigene Schlüssel, verschlüsselt), Banküberweisung mit Referenz. Der Zahlungsstatus wird immer beim Anbieter nachgefragt, Webhooks werden nicht blind geglaubt.
- **Tickets**: PDF mit QR-Code per E-Mail, Bestelllink mit Download, Druck an der Kasse.
- **Sitzplätze**: ohne Platzwahl (Kontingente), Raster (Reihen × Plätze) oder frei platzierbarer Saalplan-Editor. Doppelvergabe ist durch Sperren und einen eindeutigen Datenbank-Index ausgeschlossen.
- **Digitale Events**: Zugangslink und Hinweise sieht nur, wer bezahlt hat.
- **Abendkasse**: Kassenschichten mit Anfangsbestand, Barverkauf und Karte/TWINT-Terminal, Storno, Kassenabschluss mit Soll/Ist-Differenz, CSV-Export.
- **Einlass**: Web-Scanner mit Kamera (QR) und manueller Eingabe/Suche, atomare Einlösung mit Doppelscan-Erkennung, Live-Zähler.
- **Verwaltung**: Bestellungen filtern, Überweisungen als bezahlt markieren, stornieren, erstatten (inkl. Rückerstattung beim Anbieter), Export.
- **Plattform**: Reseller mit Whitelabel und Limits, Melden-Funktion mit Moderation (Event sperren), Update-System.

Nicht enthalten: Apple-/Google-Wallet-Pässe (benötigen Zertifikate bzw. ein Google-Konto und sind ohne diese nicht sinnvoll testbar). Das PDF mit QR-Code ersetzt sie.

## Installation

Voraussetzungen: PHP 8.3 mit `gd`, `zip`, `mbstring`, `pdo_mysql`, MySQL/MariaDB, Composer, Node 22.

```sh
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# DB-Zugang, APP_URL und Mail-Einstellungen in .env eintragen, dann:
php artisan migrate --force
php artisan storage:link
npm ci && npm run build
php artisan tickets:create-admin admin@example.ch "Admin"
```

Webroot ist `public/`. Für Reservierungsablauf und Wartelisten braucht es einen Cron-Eintrag (Plesk: «Geplante Aufgaben»):

```
* * * * * php /pfad/zur/app/artisan schedule:run >> /dev/null 2>&1
```

Für Reseller-Subdomains `TICKETS_BASE_DOMAIN` setzen (Wildcard-DNS auf die Installation); eigene Domains eines Resellers werden einfach auf dieselbe Installation gelegt.

### Eigene Domains der Reseller (CNAME)

Reseller registrieren ihre Domains selbst unter **Reseller → Domains**: Domain eintragen, beim Domain-Anbieter einen CNAME auf das Ziel der Plattform setzen (`TICKETS_CNAME_TARGET`, Standard: `TICKETS_BASE_DOMAIN` bzw. der Host von `APP_URL`) und prüfen. Die Domain wird erst aktiv, wenn der DNS-Eintrag wirklich auf die Plattform zeigt, so kann niemand fremde Domains beanspruchen. Hauptdomains ohne CNAME-Möglichkeit funktionieren mit ALIAS/ANAME oder einem A-Eintrag auf dieselbe IP. Der Scheduler prüft ausstehende Domains alle 15 Minuten; unbestätigte Einträge verfallen nach 14 Tagen. Limit pro Reseller: `TICKETS_MAX_DOMAINS` (Standard 5).

Das **HTTPS-Zertifikat** für solche Domains stellt der Webserver aus, nicht die App:

- Plesk: Domain als Alias der Installation hinzufügen und Let's Encrypt aktivieren.
- Caddy: `on_demand_tls { ask https://tickets.beispiel.ch/domain-check?token=… }` (Token in `TICKETS_DOMAIN_CHECK_TOKEN`). Der Endpunkt antwortet nur für bestätigte Mandanten-Domains mit 200.

### Zahlungs-Webhooks

Mollie und Stripe melden sich unter `/webhooks/mollie/{veranstalter}` bzw. `/webhooks/stripe/{veranstalter}`. Die URL wird beim Anlegen einer Zahlung automatisch mitgegeben. Zusätzlich prüft die Bestellseite den Status beim Anbieter, sobald der Kunde zurückkehrt.

## Web-App (PWA)

Die Plattform ist als installierbare Web-App nutzbar (Android: «Installieren», iOS: Teilen → «Zum Home-Bildschirm»). Manifest, Icons und Theme-Farbe folgen dem Branding des Resellers. Berücksichtigte Eigenheiten:

- **iOS:** kein Install-Prompt (Anleitung als Hinweis), Speicher wird nach ca. 7 Tagen ohne Nutzung geleert (nur der kleine App-Shell- und Bestellseiten-Cache, kein Datenverlust), Service-Worker-Update wird beim Start und bei jeder Rückkehr aktiv geprüft, Safe-Areas und `100dvh`, Eingabefelder mit 16 px (kein Auto-Zoom), Ton erst nach Tippen, Kamera startet nach Hintergrund automatisch neu.
- **Android:** getrennte «any»- und «maskable»-Icons, nativer QR-Detektor im Scanner (jsQR als Ersatz), kein Pull-to-Refresh in Scanner/Kasse/Editor.
- **Beide:** Bildschirm bleibt beim Scannen an (Wake Lock), Offline-Seite statt Browserfehler, Kundenbestellseiten mit QR-Tickets bleiben ohne Empfang abrufbar, Kasse und Einlass gehen bewusst immer live ans Netz, Abgelaufene Sitzung (419) zeigt eine verständliche Seite. Die Session dauert standardmässig 8 Stunden.

## Updates

Unter **Administration → Updates** prüft die Plattform das neueste Release im Repository `VylarisGroup/vylaris-tickets` (fest im Code, nicht änderbar). Ein Klick installiert es:

1. Paket laden, Prüfsumme (SHA-256) und Download-Quelle prüfen
2. Sicherung der Installation (`storage/app/backups`, die letzten drei bleiben)
3. Wartungsmodus, Dateien austauschen (`.env`, `storage` und Uploads bleiben unberührt), Migrationen
4. Bei einem Fehler: Migrationen zurück, Dateien aus der Sicherung wiederherstellen, Wartungsmodus beenden

Das Release-Paket (mit `vendor` und gebauten Assets) erstellt der Workflow `.github/workflows/release.yml` beim Veröffentlichen eines Releases.

## Entwicklung

```sh
php artisan serve
npm run dev
php artisan test
```
