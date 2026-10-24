<?php

namespace App\Services;

use App\Models\Organizer;
use Illuminate\Support\Facades\Cache;

/** Laufende Systemprüfung nach der Einrichtung (Hinweise im Admin-Bereich). */
class SystemCheck
{
    public const HEARTBEAT_KEY = 'tickets.scheduler.heartbeat';

    /** @return array<int,array{label:string,ok:bool,hint:string}> */
    public function all(): array
    {
        $checks = [];
        $add = function (string $label, bool $ok, string $hint) use (&$checks) {
            $checks[] = ['label' => $label, 'ok' => $ok, 'hint' => $hint];
        };

        $beat = Cache::get(self::HEARTBEAT_KEY);
        $add('Geplante Aufgaben (Cron) laufen', $beat && now()->timestamp - (int) $beat < 300,
            'Reservierungen laufen sonst nicht ab und die Warteliste wird nicht benachrichtigt. Lege bei deinem Hoster (Plesk: «Geplante Aufgaben») jede Minute einen Cron-Job an. Entweder als Befehl: '.$this->cronLine().($this->cronUrl() ? ' – oder als URL-Aufruf («URL abrufen»): '.$this->cronUrl() : ''));

        $add('E-Mail-Versand eingerichtet', ! in_array(config('mail.default'), ['log', 'array'], true),
            'Tickets und Bestätigungen werden nur ins Log geschrieben. Trage in der Datei .env die SMTP-Zugangsdaten (MAIL_*) ein.');

        $add('App-Adresse stimmt (APP_URL)', parse_url((string) config('app.url'), PHP_URL_HOST) === request()->getHost() || app()->runningInConsole(),
            'APP_URL ('.config('app.url').') passt nicht zur aufgerufenen Adresse. Links in E-Mails und Zahlungs-Rückleitungen könnten falsch sein.');

        $add('Sichere Verbindung (HTTPS)', request()->isSecure() || app()->runningInConsole(),
            'Ohne HTTPS funktionieren der Kamera-Scanner, die Installation als App und Zahlungen nicht zuverlässig.');

        $add('Debug-Modus ist aus', ! config('app.debug'), 'In der .env APP_DEBUG=false setzen, sonst werden Fehlerdetails öffentlich angezeigt.');

        $add('Bilder und Logos sind erreichbar (storage:link)', is_link(public_path('storage')) || is_dir(public_path('storage')),
            'Führe «php artisan storage:link» aus, damit hochgeladene Logos angezeigt werden.');

        $organizers = Organizer::count();
        if ($organizers > 0) {
            $withPayment = Organizer::whereNotNull('iban')->orWhereNotNull('payment_settings')->exists();
            $add('Mindestens ein Veranstalter kann Zahlungen annehmen', $withPayment,
                'Hinterlege bei einem Veranstalter unter «Einstellungen» Mollie-/Stripe-Schlüssel oder eine IBAN für Überweisungen.');
        }

        return $checks;
    }

    public function problems(): array
    {
        return array_values(array_filter($this->all(), fn ($c) => ! $c['ok']));
    }

    public function cronLine(): string
    {
        return '* * * * * '.PHP_BINARY.' '.base_path('artisan').' schedule:run >> /dev/null 2>&1';
    }

    /** Geheimer Token der Cron-URL; wird bei Bedarf erzeugt und in die .env geschrieben. */
    public function cronToken(): ?string
    {
        $token = (string) config('tickets.cron_token');
        if ($token !== '') {
            return $token;
        }

        $env = app(Installer::class)->env();
        if (! $env->writable()) {
            return null;
        }

        $token = bin2hex(random_bytes(20));
        try {
            $env->set(['TICKETS_CRON_TOKEN' => $token]);
        } catch (\Throwable) {
            return null;
        }
        config(['tickets.cron_token' => $token]);

        return $token;
    }

    /** Adresse, die ein externer Cron-Dienst oder Plesk («URL abrufen») jede Minute aufrufen kann. */
    public function cronUrl(): ?string
    {
        $token = $this->cronToken();

        return $token ? rtrim((string) config('app.url') ?: request()->getSchemeAndHttpHost(), '/').'/cron/'.$token : null;
    }
}
