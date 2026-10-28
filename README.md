# Vylaris Tickets

Ticketing für Veranstaltungen vor Ort und digital: Vorverkauf, Abendkasse und Einlass in einem System.
Gebaut mit Laravel 13, Blade, Alpine.js, Tailwind CSS 4 und MySQL/MariaDB. Nur Deutsch.

## Mandantenstruktur (Plesk-Prinzip)

```
Plattform-Admin
└── Reseller (Whitelabel: Name, Logo, Farbe, optional vom Betreiber zugewiesene Domain, Limits)
    └── Veranstalter (Vereine, Clubs, Firmen)
        └── Team: Inhaber · Verwalter · Kasse · Einlass
```

- Der Reseller wird über den Hostnamen erkannt (nur eine vom Betreiber zugewiesene `domain`), ohne Treffer gilt das Vylaris-Design.
- Zahlungen gehen direkt an den Veranstalter (eigene Mollie-/Stripe-Schlüssel, verschlüsselt gespeichert).
- Veranstalter ohne Reseller hängen direkt an der Plattform.
- **Tickets pro Person:** Pro Event lässt sich einschalten, dass für jedes Ticket eine Person angegeben werden muss (Name immer, dazu wählbar Geburtsdatum, Wohnort, E-Mail, Telefon). Die Angaben werden beim Kauf pro Ticket abgefragt, stehen auf dem Ticket (Name, Geburtsdatum, Wohnort), in der Teilnehmerliste samt CSV-Export und im Scanner (Geburtsdatum für Alterskontrollen). An der Abendkasse werden keine Angaben verlangt.
- **Datumsauswahl** beim Anlegen und Bearbeiten: Datum und Uhrzeit getrennt, Schnellwahl (Heute, Morgen, nächster Freitag/Samstag, gängige Uhrzeiten), Dauer per Klick, Einlass relativ zum Beginn, mehrtägige Events, Zusammenfassung mit Warnungen sowie Schnellwahl für den Vorverkauf.
- **Handscanner am Einlass:** Kabelgebundene Scanner im Tastatur-Modus (z. B. Manhattan SD313B) funktionieren direkt auf der Einlass-Seite: einfach scannen, egal wo der Cursor steht. Erkannt wird am Tempo, Tastaturlayout und Feststelltaste spielen keine Rolle. Weil einfache CCD-Scanner nur Strichcodes lesen, steht auf jedem Ticket (PDF und Bestellseite) zusätzlich ein Code-128-Strichcode neben dem QR-Code.
- Reseller laden neue Veranstalter per E-Mail ein: Der Inhaber bekommt einen Link (7 Tage gültig, nur einmal nutzbar) und legt sein Passwort selbst fest. Offene Einladungen lassen sich erneut senden oder zurückziehen. Bestehende Konten werden direkt zugeordnet.
- E-Mails (Tickets, Erinnerungen, Einladungen u. a.) tragen Logo, Farbe, Name und Support-Adresse des Resellers.
- Das Reseller-Dashboard zeigt die Limits (Veranstalter, Events pro Veranstalter) mit Warnung ab 80 %.

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

Voraussetzungen: PHP 8.3 mit `gd`, `zip`, `mbstring`, `pdo_mysql`, MySQL/MariaDB.

### Mit dem Einrichtungsassistenten (empfohlen)

1. Code auf den Server legen (Release-Zip entpacken oder `git clone`), `composer install --no-dev --optimize-autoloader`, Webroot auf `public/`.
2. Die Website im Browser öffnen. Der Assistent legt `.env` und `APP_KEY` selbst an, prüft den Server und führt durch Datenbank (inkl. Verbindungstest und Anlegen der Tabellen), Plattform, Administrator, E-Mail (mit Test-Mail) und auf Wunsch den ersten Veranstalter samt Beispiel-Event. Am Ende zeigt er die Cron-Zeile.
3. Nach dem Abschluss ist der Assistent gesperrt (`/installation` liefert 404). Bestehende Installationen mit Plattform-Administrator gelten automatisch als eingerichtet.

Sicherheit: Wer die Seite vor dir aufruft, könnte die Einrichtung durchführen. Setze deshalb bei öffentlich erreichbaren Servern vorab `TICKETS_INSTALL_TOKEN` in der `.env` (der Assistent fragt dann danach). `TICKETS_INSTALLER=false` schaltet den Assistenten ganz ab. Im Admin-Bereich zeigt ein Systemstatus offene Punkte (Cron, Mail, HTTPS, Zahlungsarten).

### Manuell

```sh
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# DB-Zugang, APP_URL und Mail-Einstellungen in .env eintragen, dann:
php artisan migrate --force
php artisan storage:link
php artisan tickets:create-admin admin@example.ch "Admin"
```

Setze dabei `TICKETS_INSTALLER=false`. Die Frontend-Assets liegen gebaut im Repository (`public/build`), `npm` ist nur für Entwicklung nötig.

Webroot ist `public/`. Für Reservierungsablauf und Wartelisten braucht es einen Cron-Eintrag (Plesk: «Geplante Aufgaben»):

```
* * * * * php /pfad/zur/app/artisan schedule:run >> /dev/null 2>&1
```

**Cron ohne Befehlszeile:** Bietet dein Hoster nur «URL abrufen» (Plesk: «Geplante Aufgaben → Aufgabentyp: URL abrufen») oder einen externen Cron-Dienst, rufe jede Minute `https://deine-domain/cron/{TICKETS_CRON_TOKEN}` auf. Der Token wird automatisch in der `.env` angelegt. Der Assistent und der Systemstatus im Admin-Bereich zeigen die fertige Adresse. Ein falscher Token liefert 404.

Reseller können weder eigene Domains noch Subdomains registrieren; der Betreiber kann einem Reseller aber unter Administration → Reseller eine eigene Domain zuweisen, die er selbst im Webserver einrichtet.

### Zahlungs-Webhooks

Mollie und Stripe melden sich unter `/webhooks/mollie/{veranstalter}` bzw. `/webhooks/stripe/{veranstalter}`. Die URL wird beim Anlegen einer Zahlung automatisch mitgegeben. Zusätzlich prüft die Bestellseite den Status beim Anbieter, sobald der Kunde zurückkehrt.

## Design und Dunkelmodus

Die Oberfläche ist standardmässig dunkel; über den Schalter in der Kopfzeile wechselt man zwischen Dunkel, Hell und Automatisch (System). Die Wahl wird im Browser gespeichert. Farben sind als CSS-Variablen in `resources/css/app.css` definiert, die Slate-Skala wird im Dunkelmodus invertiert, bestehende Klassen folgen automatisch. Die Markenfarbe eines Resellers wird im Dunkelmodus leicht aufgehellt. Hover-Effekte gelten nur für Geräte mit Maus (`hover: hover`), damit Touch-Geräte und die installierte Web-App keinen Doppeltipp brauchen. Die Schrift (Inter) wird lokal mitgeliefert.

## Web-App (PWA)

Die Plattform ist als installierbare Web-App nutzbar (Android: «Installieren», iOS: Teilen → «Zum Home-Bildschirm»). Manifest, Icons und Theme-Farbe folgen dem Branding des Resellers. Berücksichtigte Eigenheiten:

- **iOS:** kein Install-Prompt (Anleitung als Hinweis), Speicher wird nach ca. 7 Tagen ohne Nutzung geleert (nur der kleine App-Shell- und Bestellseiten-Cache, kein Datenverlust), Service-Worker-Update wird beim Start und bei jeder Rückkehr aktiv geprüft, Safe-Areas und `100dvh`, Eingabefelder mit 16 px (kein Auto-Zoom), Ton erst nach Tippen, Kamera startet nach Hintergrund automatisch neu.
- **Android:** getrennte «any»- und «maskable»-Icons, nativer QR-Detektor im Scanner (jsQR als Ersatz), kein Pull-to-Refresh in Scanner/Kasse/Editor.
- **Beide:** Bildschirm bleibt beim Scannen an (Wake Lock), Offline-Seite statt Browserfehler, Kundenbestellseiten mit QR-Tickets bleiben ohne Empfang abrufbar, Kasse und Einlass gehen bewusst immer live ans Netz, Abgelaufene Sitzung (419) zeigt eine verständliche Seite. Die Session dauert standardmässig 8 Stunden.

## Updates

Unter **Administration → Updates** prüft die Plattform das neueste GitHub-Release im Repository `VylarisGroup/vylaris-tickets` (fest im Code, nicht änderbar). Ein Klick installiert es. Verwendet wird die von GitHub automatisch erzeugte ZIP-Datei des Releases; das Vertrauen beruht allein auf der HTTPS-Verbindung zu GitHub (keine Prüfsumme, Weiterleitungen nur über HTTPS).

1. ZIP laden und entpacken (mit Schutz vor manipulierten Pfaden)
2. Hat sich `composer.lock` geändert, werden die PHP-Abhängigkeiten im entpackten Paket beschafft, **bevor** etwas an der Installation verändert wird: zuerst per Composer (gesucht in den üblichen Verzeichnissen, notfalls `TICKETS_COMPOSER=/pfad/zu/composer` in `.env`), sonst über das fertige `vendor.zip` am Release (wird vom Workflow «Vendor-Paket» angehängt, für ein bestehendes Release dort manuell mit dem Tag starten). Gelingt beides nicht, bricht das Update ohne Änderung ab. Sonst bleibt `vendor` unverändert.

Scheitert das Update in der Administration, lässt es sich auf dem Server neu anstossen: `php artisan tickets:update` (mit `--check` nur prüfen). Die Update-Seite zeigt, welchen Composer und welches PHP der Webserver findet.
3. Sicherung der Installation (`storage/app/backups`, die letzten drei bleiben)
4. Wartungsmodus, Dateien austauschen (`.env`, `storage` und Uploads bleiben unberührt), Migrationen
5. Bei einem Fehler: Migrationen zurück, Dateien aus der Sicherung wiederherstellen, Wartungsmodus beenden

Die Version einer Installation ist der Release-Tag (z. B. `v1.19.0`). Da die ZIP-Datei keinen Node-Build enthält, liegen die gebauten Assets (`public/build`) im Repository. Der Workflow `.github/workflows/build-assets.yml` baut und committet sie automatisch, sobald sich Frontend-Dateien ändern. Vor dem Veröffentlichen eines Releases sollte dieser Lauf durch sein.

## Entwicklung

```sh
php artisan serve
npm run dev
php artisan test
```

## Funktionen im Überblick

- **Hallenplan**: Grundriss als Bild oder PDF hochladen, Sitze und Tische als Marker setzen, mehrere Ebenen (Stockwerke/Räume). Tische sind pro Tisch «nur ganz» oder mit Einzelplätzen buchbar. Besetzt ist ein Platz, sobald die Bestellung bezahlt ist oder unbezahlt noch reserviert (Überweisungsfrist bzw. Zahlungsfenster).
- **Push-Benachrichtigungen** für die PWA (Profilmenü → Benachrichtigungen): neue Bestellung, Überweisung wartet, ausverkauft, Warteliste, Meldungen und neue Version. Je Gerät und Kategorie einstellbar. Benötigt HTTPS; auf iOS die installierte App. VAPID-Schlüssel werden automatisch erzeugt (oder `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY` in `.env`).
- **Ticket-PDF** mit Titelbild-Kopf, QR-Abschnitt und Infobereich in den Markenfarben des Veranstalters.
- **Event-Assistent**: Event, Tickets, Verkauf und Veröffentlichung in fünf Schritten.
- **Seitenleisten** je Rolle (Veranstalter, Reseller, Administration), nach Kategorien gegliedert.
- **Entdecken**: Kategorien, Stadt, Titelbild, Suche und Filter auf der Startseite.
- **Auswertung**: Statistik (Umsatz, Verlauf, Zahlarten, Kanäle), Teilnehmerliste mit CSV-Export und manuellem Check-in.
- **Kommunikation**: Nachricht an alle Käufer eines Events (inkl. Testversand).
- **CRUD**: Events (inkl. Duplizieren), Gutscheine, Veranstalter, Benutzer, Eigenes Konto.
- **Tickets**: überarbeitetes Design online und als PDF.
- **Sichtbarkeit**: Private Events (nicht in Listen) mit optionalem Zugangsschlüssel.
- **Rechtliches**: AGB- und Datenschutz-Links pro Veranstalter mit Zustimmung beim Kauf.
- **Reichweite**: Kalender-Export (.ics, Google Kalender), Google-Eventdaten (schema.org) und Social-Vorschau, Sponsoren-Logos.
- **Benachrichtigungen**: automatische Erinnerung an Käufer vor dem Event, Verkaufsreport per E-Mail (täglich oder wöchentlich).
- **Einlass**: Ausgang erfassen und Wiedereintritt, Anzeige «aktuell im Haus».
- **Kunden**: Kundenverwaltung mit CSV-Export. Ticketdesign mit eigenem Logo, Farbe und Hinweistext.
- **Einlass-Anzeige**: Der PC zeigt Scan-Ergebnisse gross an, ein per QR-Code oder 6-stelligem Code gekoppeltes Smartphone dient nur als Kamera (Einlass → «Anzeige am PC, Handy als Scanner»).
