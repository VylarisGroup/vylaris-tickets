<?php

namespace App\Http\Controllers\Install;

use App\Enums\OrganizerRole;
use App\Enums\ResellerRole;
use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Reseller;
use App\Models\User;
use App\Services\Installer;
use App\Services\SystemCheck;
use App\Support\Slugger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Einrichtungsassistent beim ersten Start. */
class InstallController extends Controller
{
    public const STEPS = [
        'welcome' => 'Start', 'database' => 'Datenbank', 'platform' => 'Plattform', 'admin' => 'Administrator',
        'mail' => 'E-Mail', 'tenants' => 'Mandanten', 'done' => 'Fertig',
    ];

    public function __construct(private Installer $installer) {}

    // --- Start ------------------------------------------------------------------------------------

    public function welcome(Request $request)
    {
        return view('install.welcome', [
            'step' => 'welcome',
            'needsToken' => $this->needsToken(),
            'requirements' => $this->installer->requirements(),
            'ok' => $this->installer->requirementsMet(),
        ]);
    }

    public function token(Request $request): RedirectResponse
    {
        $expected = (string) config('tickets.install_token');
        $given = (string) $request->input('token');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return back()->withErrors(['token' => 'Das Setup-Token stimmt nicht. Es steht in der Datei .env unter TICKETS_INSTALL_TOKEN.']);
        }

        $request->session()->put('install.token_ok', true);

        return redirect()->route('install.welcome');
    }

    // --- Datenbank --------------------------------------------------------------------------------

    public function database(Request $request)
    {
        if ($redirect = $this->reach('database')) {
            return $redirect;
        }

        $env = $this->installer->env();

        return view('install.database', [
            'step' => 'database',
            'values' => [
                'host' => $env->get('DB_HOST') ?: '127.0.0.1', 'port' => $env->get('DB_PORT') ?: '3306',
                'database' => $env->get('DB_DATABASE') ?: 'vylaris_tickets', 'username' => $env->get('DB_USERNAME') ?: '',
            ],
            'ready' => $this->installer->databaseReady(),
        ]);
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        if ($redirect = $this->reach('database')) {
            return $redirect;
        }

        $data = $request->validate([
            'host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_$\-]+$/'],
            'username' => ['required', 'string', 'max:80'],
            'db_password' => ['nullable', 'string', 'max:200', 'not_regex:/[\r\n\0]/'],
        ], ['host.regex' => 'Der Server-Name enthält ungültige Zeichen.', 'database.regex' => 'Der Datenbankname darf nur Buchstaben, Ziffern, _ und - enthalten.']);

        $db = ['host' => $data['host'], 'port' => (int) $data['port'], 'database' => $data['database'], 'username' => $data['username'], 'password' => (string) ($data['db_password'] ?? '')];

        $error = $this->installer->testConnection($db, $request->boolean('create'));

        if ($error) {
            return back()->withInput($request->except('db_password'))->withErrors(['db' => $error]);
        }

        if ($request->input('action') === 'test') {
            return back()->withInput($request->except('db_password'))->with('status', 'Verbindung erfolgreich. Du kannst jetzt speichern und die Datenbank einrichten.');
        }

        try {
            $this->installer->env()->set([
                'DB_CONNECTION' => 'mysql', 'DB_HOST' => $db['host'], 'DB_PORT' => $db['port'],
                'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'],
            ]);
        } catch (\Throwable $e) {
            return back()->withInput($request->except('db_password'))->withErrors(['db' => $e->getMessage()]);
        }

        $this->installer->applyConnection($db);
        $this->installer->markInProgress();

        try {
            $this->installer->migrate();
        } catch (\Throwable $e) {
            return back()->withInput($request->except('db_password'))->withErrors([
                'db' => 'Die Tabellen konnten nicht angelegt werden: '.mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 300).' Tipp: Verwende eine leere Datenbank, die nicht von einer anderen Anwendung genutzt wird.',
            ]);
        }

        return redirect()->route('install.platform')->with('status', 'Datenbank eingerichtet. Alle Tabellen wurden angelegt.');
    }

    // --- Plattform --------------------------------------------------------------------------------

    public function platform(Request $request)
    {
        if ($redirect = $this->reach('platform')) {
            return $redirect;
        }

        $env = $this->installer->env();

        return view('install.platform', [
            'step' => 'platform',
            'values' => [
                'name' => $env->get('TICKETS_NAME') ?: 'Vylaris Tickets',
                'color' => $env->get('TICKETS_BRAND_COLOR') ?: '#1867F8',
                'url' => rtrim($request->getSchemeAndHttpHost(), '/'),
            ],
        ]);
    }

    public function savePlatform(Request $request): RedirectResponse
    {
        if ($redirect = $this->reach('platform')) {
            return $redirect;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'not_regex:/[\r\n\0]/'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'url' => ['required', 'url', 'max:190'],
        ], ['color.regex' => 'Bitte einen Farbwert wie #1867F8 eingeben.']);

        try {
            $this->installer->env()->set([
                'APP_NAME' => $data['name'], 'TICKETS_NAME' => $data['name'], 'TICKETS_BRAND_COLOR' => strtoupper($data['color']),
                'APP_URL' => rtrim($data['url'], '/'),
                'TICKETS_ALLOW_REGISTRATION' => $request->boolean('allow_registration') ? 'true' : 'false',
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()->route('install.admin');
    }

    // --- Administrator ----------------------------------------------------------------------------

    public function admin(Request $request)
    {
        if ($redirect = $this->reach('admin')) {
            return $redirect;
        }

        return view('install.admin', ['step' => 'admin', 'exists' => $this->installer->adminExists(), 'loggedIn' => $this->isAdmin()]);
    }

    public function saveAdmin(Request $request): RedirectResponse
    {
        if ($redirect = $this->reach('admin')) {
            return $redirect;
        }

        // Ein zweiter Administrator kann über den Assistenten nie angelegt werden.
        abort_if($this->installer->adminExists(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(10)],
        ], ['password.min' => 'Das Passwort braucht mindestens 10 Zeichen.', 'password.confirmed' => 'Die Passwörter stimmen nicht überein.']);

        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('install.mail');
    }

    /** Wiederaufnahme, falls die Sitzung zwischendurch verloren ging. */
    public function login(Request $request): RedirectResponse
    {
        if ($redirect = $this->reach('login')) {
            return $redirect;
        }

        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($credentials) || ! Auth::user()->is_platform_admin) {
            Auth::logout();

            return back()->withErrors(['email' => 'Anmeldung nicht möglich. Bitte mit den Zugangsdaten des Administrators anmelden.']);
        }

        $request->session()->regenerate();

        return redirect()->route('install.mail');
    }

    // --- E-Mail -----------------------------------------------------------------------------------

    public function mail(Request $request)
    {
        if ($redirect = $this->reach('mail')) {
            return $redirect;
        }

        $env = $this->installer->env();

        return view('install.mail', [
            'step' => 'mail',
            'values' => [
                'host' => $env->get('MAIL_MAILER') === 'smtp' ? $env->get('MAIL_HOST') : '', 'port' => $env->get('MAIL_PORT') ?: '587',
                'username' => $env->get('MAIL_USERNAME') ?: '', 'from_address' => $env->get('MAIL_FROM_ADDRESS') ?: '',
                'from_name' => $env->get('TICKETS_NAME') ?: config('tickets.name'),
            ],
            'configured' => $env->get('MAIL_MAILER') === 'smtp',
        ]);
    }

    public function saveMail(Request $request): RedirectResponse
    {
        if ($redirect = $this->reach('mail')) {
            return $redirect;
        }

        if ($request->input('action') === 'skip') {
            return redirect()->route('install.tenants')->with('status', 'E-Mail übersprungen. Bis zur Einrichtung werden E-Mails nur ins Log geschrieben.');
        }

        $data = $request->validate([
            'host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:190', 'not_regex:/[\r\n\0]/'],
            'mail_password' => ['nullable', 'string', 'max:200', 'not_regex:/[\r\n\0]/'],
            'encryption' => ['required', 'in:tls,ssl,none'],
            'from_address' => ['required', 'email', 'max:190'],
            'from_name' => ['required', 'string', 'max:120', 'not_regex:/[\r\n\0]/'],
        ], ['host.regex' => 'Der Server-Name enthält ungültige Zeichen.']);

        $smtp = [
            'transport' => 'smtp', 'host' => $data['host'], 'port' => (int) $data['port'],
            'username' => $data['username'] ?: null, 'password' => $data['mail_password'] ?: null,
            'scheme' => $data['encryption'] === 'ssl' ? 'smtps' : 'smtp', 'timeout' => 15,
        ];

        if ($request->input('action') === 'test') {
            try {
                config(['mail.mailers.install_test' => $smtp, 'mail.from' => ['address' => $data['from_address'], 'name' => $data['from_name']]]);
                Mail::mailer('install_test')->raw('Dies ist eine Test-E-Mail aus dem Einrichtungsassistenten. Der E-Mail-Versand funktioniert.', function ($m) {
                    $m->to(Auth::user()->email)->subject('Test-E-Mail');
                });
            } catch (\Throwable $e) {
                return back()->withInput($request->except('mail_password'))->withErrors(['mail' => $this->explainMail($e->getMessage())]);
            }

            return back()->withInput($request->except('mail_password'))->with('status', 'Test-E-Mail wurde an '.Auth::user()->email.' gesendet. Bitte prüfe dein Postfach (auch den Spam-Ordner).');
        }

        try {
            $this->installer->env()->set([
                'MAIL_MAILER' => 'smtp', 'MAIL_HOST' => $data['host'], 'MAIL_PORT' => $data['port'],
                'MAIL_USERNAME' => (string) ($data['username'] ?? ''), 'MAIL_PASSWORD' => (string) ($data['mail_password'] ?? ''),
                'MAIL_SCHEME' => $smtp['scheme'] === 'smtps' ? 'smtps' : '',
                'MAIL_FROM_ADDRESS' => $data['from_address'], 'MAIL_FROM_NAME' => $data['from_name'],
            ]);
        } catch (\Throwable $e) {
            return back()->withInput($request->except('mail_password'))->withErrors(['mail' => $e->getMessage()]);
        }

        return redirect()->route('install.tenants')->with('status', 'E-Mail-Einstellungen gespeichert.');
    }

    // --- Mandanten --------------------------------------------------------------------------------

    public function tenants(Request $request)
    {
        if ($redirect = $this->reach('tenants')) {
            return $redirect;
        }

        return view('install.tenants', ['step' => 'tenants', 'exists' => Organizer::exists()]);
    }

    public function saveTenants(Request $request): RedirectResponse
    {
        if ($redirect = $this->reach('tenants')) {
            return $redirect;
        }

        if ($request->input('action') === 'skip' || Organizer::exists()) {
            return redirect()->route('install.done');
        }

        $data = $request->validate([
            'use_reseller' => ['nullable', 'boolean'],
            'reseller_name' => ['nullable', 'required_if:use_reseller,1', 'string', 'max:120'],
            'organizer_name' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'in:CHF,EUR'],
            'iban' => ['nullable', 'string', 'max:40'],
            'vat_enabled' => ['nullable', 'boolean'],
            'vat_rate' => ['nullable', 'numeric', 'between:0,30'],
            'demo_event' => ['nullable', 'boolean'],
        ], ['reseller_name.required_if' => 'Bitte einen Namen für den Reseller eingeben.', 'organizer_name.required' => 'Bitte einen Namen für den Veranstalter eingeben.']);

        $admin = Auth::user();

        DB::transaction(function () use ($data, $admin, $request) {
            $reseller = null;

            if ($request->boolean('use_reseller')) {
                $reseller = Reseller::create(['name' => $data['reseller_name'], 'slug' => Slugger::unique(Reseller::class, $data['reseller_name'])]);
                $reseller->users()->attach($admin->id, ['role' => ResellerRole::Owner->value]);
            }

            $organizer = Organizer::create([
                'reseller_id' => $reseller?->id, 'name' => $data['organizer_name'], 'slug' => Slugger::unique(Organizer::class, $data['organizer_name']),
                'default_currency' => $data['currency'], 'iban' => ($data['iban'] ?? null) ?: null,
                'vat_enabled' => $request->boolean('vat_enabled'), 'vat_rate' => $request->boolean('vat_enabled') ? ($data['vat_rate'] ?? 8.1) : 0,
            ]);
            $organizer->users()->attach($admin->id, ['role' => OrganizerRole::Owner->value]);

            if ($request->boolean('demo_event')) {
                $event = $organizer->events()->create([
                    'title' => 'Beispiel-Event', 'slug' => 'beispiel-event', 'type' => 'onsite', 'status' => 'draft',
                    'venue_name' => 'Veranstaltungsort', 'starts_at' => now()->addMonth()->setTime(19, 30), 'currency' => $data['currency'],
                    'door_sales_enabled' => true, 'description' => 'Dies ist ein Beispiel. Passe es an oder lösche es.',
                ]);
                $event->ticketTypes()->create(['name' => 'Eintritt', 'price_cents' => 2500, 'quota' => 100, 'max_per_order' => 8]);
            }
        });

        return redirect()->route('install.done');
    }

    // --- Abschluss --------------------------------------------------------------------------------

    public function done(Request $request)
    {
        if ($redirect = $this->reach('done')) {
            return $redirect;
        }

        $organizer = Organizer::orderBy('id')->first();

        return view('install.done', [
            'step' => 'done',
            'organizer' => $organizer,
            'cron' => app(SystemCheck::class)->cronLine(),
            'cronUrl' => app(SystemCheck::class)->cronUrl(),
            'mailConfigured' => $this->installer->env()->get('MAIL_MAILER') === 'smtp',
        ]);
    }

    public function finish(Request $request): RedirectResponse
    {
        if ($redirect = $this->reach('done')) {
            return $redirect;
        }

        try {
            $this->installer->env()->set(['APP_ENV' => 'production', 'APP_DEBUG' => 'false']);
        } catch (\Throwable) {
            // nicht kritisch: der Hinweis erscheint später im Admin-Bereich
        }

        try {
            if (! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
                Artisan::call('storage:link');
            }
            Artisan::call('config:clear');
        } catch (\Throwable) {
            // wird im Systemstatus angezeigt
        }

        $this->installer->markInstalled();

        return redirect()->route('dashboard')->with('status', 'Die Einrichtung ist abgeschlossen. Willkommen!');
    }

    // ---------------------------------------------------------------------------------------------

    private function needsToken(): bool
    {
        return filled(config('tickets.install_token')) && ! session('install.token_ok');
    }

    private function isAdmin(): bool
    {
        return Auth::check() && Auth::user()->is_platform_admin;
    }

    /** Stellt die Reihenfolge sicher und gibt bei fehlenden Voraussetzungen den passenden früheren Schritt zurück. */
    private function reach(string $step): ?RedirectResponse
    {
        if ($this->needsToken()) {
            return redirect()->route('install.welcome');
        }

        if (in_array($step, ['database', 'platform', 'admin', 'login', 'mail', 'tenants', 'done'], true) && ! $this->installer->requirementsMet()) {
            return redirect()->route('install.welcome');
        }

        if (in_array($step, ['platform', 'admin', 'login', 'mail', 'tenants', 'done'], true) && ! $this->installer->databaseReady()) {
            return redirect()->route('install.database');
        }

        if (in_array($step, ['mail', 'tenants', 'done'], true)) {
            if (! $this->installer->adminExists()) {
                return redirect()->route('install.admin');
            }
            if (! $this->isAdmin()) {
                return redirect()->route('install.admin');
            }
        }

        return null;
    }

    private function explainMail(string $message): string
    {
        $m = strtolower($message);

        return match (true) {
            str_contains($m, 'authenticat') || str_contains($m, '535') => 'Anmeldung am Mail-Server fehlgeschlagen: Benutzername oder Passwort stimmen nicht.',
            str_contains($m, 'connection could not be established') || str_contains($m, 'connection refused') || str_contains($m, 'timed out') => 'Der Mail-Server ist nicht erreichbar. Prüfe Host, Port und Verschlüsselung (587 mit TLS oder 465 mit SSL). Manche Hoster sperren ausgehende Verbindungen.',
            str_contains($m, 'ssl') || str_contains($m, 'tls') || str_contains($m, 'certificate') => 'Die verschlüsselte Verbindung ist fehlgeschlagen. Probiere die andere Verschlüsselung: Port 587 mit TLS oder Port 465 mit SSL.',
            default => 'Der Versand ist fehlgeschlagen: '.mb_substr(preg_replace('/\s+/', ' ', $message), 0, 220),
        };
    }
}
