<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Organizer;
use App\Http\Controllers\Reseller;
use Illuminate\Support\Facades\Route;

Route::get('/', \App\Http\Controllers\HomeController::class)->name('home');

// Einrichtungsassistent (nach der Einrichtung gesperrt, siehe RequireInstallation)
Route::prefix('installation')->name('install.')->group(function () {
    $c = \App\Http\Controllers\Install\InstallController::class;
    Route::get('/', [$c, 'welcome'])->name('welcome');
    Route::post('/token', [$c, 'token'])->middleware('throttle:10,1')->name('token');
    Route::get('/datenbank', [$c, 'database'])->name('database');
    Route::post('/datenbank', [$c, 'saveDatabase'])->middleware('throttle:30,1')->name('database.save');
    Route::get('/plattform', [$c, 'platform'])->name('platform');
    Route::post('/plattform', [$c, 'savePlatform'])->name('platform.save');
    Route::get('/administrator', [$c, 'admin'])->name('admin');
    Route::post('/administrator', [$c, 'saveAdmin'])->middleware('throttle:10,1')->name('admin.save');
    Route::post('/anmelden', [$c, 'login'])->middleware('throttle:10,1')->name('login');
    Route::get('/mail', [$c, 'mail'])->name('mail');
    Route::post('/mail', [$c, 'saveMail'])->middleware('throttle:20,1')->name('mail.save');
    Route::get('/mandanten', [$c, 'tenants'])->name('tenants');
    Route::post('/mandanten', [$c, 'saveTenants'])->name('tenants.save');
    Route::get('/fertig', [$c, 'done'])->name('done');
    Route::post('/fertig', [$c, 'finish'])->name('finish');
});

// Web-App (PWA)
Route::get('/manifest.webmanifest', [\App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/pwa-icon/{purpose}-{size}.png', [\App\Http\Controllers\PwaController::class, 'icon'])->whereNumber('size')->name('pwa.icon');
Route::get('/sw.js', [\App\Http\Controllers\PwaController::class, 'serviceWorker'])->name('pwa.sw');
// Scheduler per URL auslösen (für Hoster ohne Befehlszeilen-Cron)
Route::match(['get', 'post'], '/cron/{token}', \App\Http\Controllers\CronController::class)->middleware('throttle:30,1')->name('cron');
// Für Reverse-Proxys mit automatischem Zertifikat (z. B. Caddy «on_demand_tls ask»): 200, wenn die Domain zu einem Mandanten gehört.
Route::get('/domain-check', \App\Http\Controllers\PwaController::class.'@domainCheck')->middleware('throttle:120,1')->name('domain-check');
Route::get('/offline', [\App\Http\Controllers\PwaController::class, 'offline'])->name('pwa.offline');

// Öffentlicher Shop
Route::scopeBindings()->prefix('v/{organizer:slug}')->name('shop.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Shop\ShopController::class, 'organizer'])->name('organizer');
    Route::get('/{event:slug}', [\App\Http\Controllers\Shop\ShopController::class, 'event'])->name('event');
    Route::post('/{event:slug}/bestellen', [\App\Http\Controllers\Shop\ShopController::class, 'order'])->middleware('throttle:20,1')->name('order');
    Route::post('/{event:slug}/warteliste', [\App\Http\Controllers\Shop\ShopController::class, 'waitlist'])->middleware('throttle:10,1')->name('waitlist');
    Route::post('/{event:slug}/melden', [\App\Http\Controllers\Shop\ShopController::class, 'report'])->middleware('throttle:5,1')->name('report');
});

Route::get('/bestellung/{token}', [\App\Http\Controllers\Shop\OrderController::class, 'show'])->name('order.show');
Route::get('/bestellung/{token}/tickets.pdf', [\App\Http\Controllers\Shop\OrderController::class, 'pdf'])->name('order.pdf');

Route::post('/webhooks/mollie/{organizer:slug}', [\App\Http\Controllers\Webhook\PaymentWebhookController::class, 'mollie'])->name('webhooks.mollie');
Route::post('/webhooks/stripe/{organizer:slug}', [\App\Http\Controllers\Webhook\PaymentWebhookController::class, 'stripe'])->name('webhooks.stripe');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/registrieren', [RegisterController::class, 'create'])->name('register');
    Route::post('/registrieren', [RegisterController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/mandant/{type}/{id}', [DashboardController::class, 'switch'])->whereNumber('id')->name('tenant.switch');
    Route::get('/konto', [\App\Http\Controllers\AccountController::class, 'edit'])->name('account.edit');
    Route::put('/konto', [\App\Http\Controllers\AccountController::class, 'update'])->name('account.update');
    Route::put('/konto/passwort', [\App\Http\Controllers\AccountController::class, 'password'])->middleware('throttle:10,1')->name('account.password');

    // Plattform-Administration
    Route::prefix('admin')->name('admin.')->middleware('can:platform-admin')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::resource('resellers', Admin\ResellerController::class)->except('show');
        Route::resource('users', Admin\UserController::class)->except('show');
        Route::get('/veranstalter', [Admin\OrganizerController::class, 'index'])->name('organizers.index');
        Route::get('/veranstalter/{organizer:slug}/bearbeiten', [Admin\OrganizerController::class, 'edit'])->name('organizers.edit');
        Route::put('/veranstalter/{organizer:slug}', [Admin\OrganizerController::class, 'update'])->name('organizers.update');
        Route::delete('/veranstalter/{organizer:slug}', [Admin\OrganizerController::class, 'destroy'])->name('organizers.destroy');
        Route::get('/meldungen', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::patch('/meldungen/{report}', [Admin\ReportController::class, 'update'])->name('reports.update');
        Route::get('/update', [Admin\UpdateController::class, 'index'])->name('update');
        Route::post('/update', [Admin\UpdateController::class, 'install'])->name('update.install');
    });

    // Reseller-Bereich (Plesk-Prinzip: Reseller verwalten ihre eigenen Veranstalter)
    Route::prefix('reseller/{reseller:slug}')->name('reseller.')->middleware('can:manage-reseller,reseller')->group(function () {
        Route::get('/', Reseller\DashboardController::class)->name('dashboard');
        Route::get('/veranstalter', [Reseller\OrganizerController::class, 'index'])->name('organizers.index');
        Route::get('/veranstalter/neu', [Reseller\OrganizerController::class, 'create'])->name('organizers.create');
        Route::post('/veranstalter', [Reseller\OrganizerController::class, 'store'])->name('organizers.store');
        Route::patch('/veranstalter/{organizer:slug}/sperren', [Reseller\OrganizerController::class, 'toggle'])->name('organizers.toggle');
        Route::get('/veranstalter/{organizer:slug}/bearbeiten', [Reseller\OrganizerController::class, 'edit'])->name('organizers.edit');
        Route::put('/veranstalter/{organizer:slug}', [Reseller\OrganizerController::class, 'update'])->name('organizers.update');
        Route::delete('/veranstalter/{organizer:slug}', [Reseller\OrganizerController::class, 'destroy'])->name('organizers.destroy');
        Route::get('/domains', [Reseller\DomainController::class, 'index'])->name('domains.index');
        Route::post('/domains', [Reseller\DomainController::class, 'store'])->middleware('throttle:20,1')->name('domains.store');
        Route::post('/domains/{domain}/pruefen', [Reseller\DomainController::class, 'verify'])->middleware('throttle:10,1')->scopeBindings()->name('domains.verify');
        Route::delete('/domains/{domain}', [Reseller\DomainController::class, 'destroy'])->scopeBindings()->name('domains.destroy');
        Route::get('/branding', [Reseller\BrandingController::class, 'edit'])->name('branding.edit');
        Route::put('/branding', [Reseller\BrandingController::class, 'update'])->name('branding.update');
    });

    // Veranstalter-Bereich
    Route::prefix('veranstalter/{organizer:slug}')->name('organizer.')->middleware('can:access-organizer,organizer')->group(function () {
        Route::get('/', Organizer\DashboardController::class)->name('dashboard');

        Route::scopeBindings()->group(function () {
            Route::middleware('can:sell-organizer,organizer')->prefix('kasse')->name('door.')->group(function () {
                $door = Organizer\DoorController::class;
                Route::get('/', [$door, 'index'])->name('index');
                Route::get('/{event:slug}', [$door, 'show'])->name('show');
                Route::post('/{event:slug}/oeffnen', [$door, 'open'])->name('open');
                Route::post('/{event:slug}/verkaufen', [$door, 'sell'])->name('sell');
                Route::get('/{event:slug}/schichten', [$door, 'sessions'])->name('sessions');
                Route::get('/{event:slug}/bestellung/{order}', [$door, 'receipt'])->name('receipt');
                Route::get('/{event:slug}/bestellung/{order}/tickets.pdf', [$door, 'pdf'])->name('pdf');
                Route::delete('/{event:slug}/bestellung/{order}', [$door, 'void'])->name('void');
                Route::post('/{event:slug}/schichten/{session}/abschliessen', [$door, 'close'])->name('close');
                Route::get('/{event:slug}/schichten/{session}', [$door, 'report'])->name('report');
                Route::get('/{event:slug}/schichten/{session}/export', [$door, 'export'])->name('export');
            });

            Route::middleware('can:scan-organizer,organizer')->prefix('einlass')->name('scan.')->group(function () {
                $scan = Organizer\ScanController::class;
                Route::get('/', [$scan, 'index'])->name('index');
                Route::get('/{event:slug}', [$scan, 'show'])->name('show');
                Route::post('/{event:slug}/pruefen', [$scan, 'check'])->middleware('throttle:240,1')->name('check');
                Route::get('/{event:slug}/suche', [$scan, 'search'])->middleware('throttle:120,1')->name('search');
                Route::post('/{event:slug}/tickets/{ticket}/einloesen', [$scan, 'admitTicket'])->name('admit');
                Route::post('/{event:slug}/tickets/{ticket}/zuruecksetzen', [$scan, 'undo'])->name('undo');
            });
        });

        Route::middleware('can:manage-organizer,organizer')->group(function () {
            Route::get('/einstellungen', [Organizer\SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/einstellungen', [Organizer\SettingsController::class, 'update'])->name('settings.update');
            Route::get('/assistent', [Organizer\WizardController::class, 'show'])->name('wizard');
            Route::post('/assistent', [Organizer\WizardController::class, 'store'])->name('wizard.store');
            Route::get('/statistik', [Organizer\StatsController::class, 'index'])->name('stats');
            Route::get('/bestellungen', [Organizer\OrderController::class, 'all'])->name('orders.all');
            Route::get('/team', [Organizer\MemberController::class, 'index'])->name('members.index');
            Route::post('/team', [Organizer\MemberController::class, 'store'])->name('members.store');
            Route::put('/team/{user}', [Organizer\MemberController::class, 'update'])->name('members.update');
            Route::delete('/team/{user}', [Organizer\MemberController::class, 'destroy'])->name('members.destroy');

            Route::scopeBindings()->group(function () {
                Route::get('/events', [Organizer\EventController::class, 'index'])->name('events.index');
                Route::get('/events/neu', [Organizer\EventController::class, 'create'])->name('events.create');
                Route::post('/events', [Organizer\EventController::class, 'store'])->name('events.store');
                Route::get('/events/{event:slug}', [Organizer\EventController::class, 'show'])->name('events.show');
                Route::get('/events/{event:slug}/bearbeiten', [Organizer\EventController::class, 'edit'])->name('events.edit');
                Route::put('/events/{event:slug}', [Organizer\EventController::class, 'update'])->name('events.update');
                Route::patch('/events/{event:slug}/status', [Organizer\EventController::class, 'status'])->name('events.status');
                Route::delete('/events/{event:slug}', [Organizer\EventController::class, 'destroy'])->name('events.destroy');
                Route::post('/events/{event:slug}/duplizieren', [Organizer\EventController::class, 'duplicate'])->name('events.duplicate');

                Route::get('/events/{event:slug}/teilnehmer', [Organizer\AttendeeController::class, 'index'])->name('attendees.index');
                Route::get('/events/{event:slug}/teilnehmer/export', [Organizer\AttendeeController::class, 'export'])->name('attendees.export');
                Route::patch('/events/{event:slug}/teilnehmer/{ticket}', [Organizer\AttendeeController::class, 'toggle'])->name('attendees.toggle');
                Route::get('/events/{event:slug}/nachricht', [Organizer\MessageController::class, 'create'])->name('messages.create');
                Route::post('/events/{event:slug}/nachricht', [Organizer\MessageController::class, 'store'])->middleware('throttle:6,1')->name('messages.store');

                Route::get('/events/{event:slug}/sitzplan', [Organizer\SeatingController::class, 'show'])->name('seating.show');
                Route::put('/events/{event:slug}/sitzplan/modus', [Organizer\SeatingController::class, 'mode'])->name('seating.mode');
                Route::post('/events/{event:slug}/sitzplan/bereiche', [Organizer\SeatingController::class, 'storeSection'])->name('seating.sections.store');
                Route::put('/events/{event:slug}/sitzplan/bereiche/{section}', [Organizer\SeatingController::class, 'updateSection'])->name('seating.sections.update');
                Route::delete('/events/{event:slug}/sitzplan/bereiche/{section}', [Organizer\SeatingController::class, 'destroySection'])->name('seating.sections.destroy');
                Route::patch('/events/{event:slug}/sitzplan/plaetze/{seat}', [Organizer\SeatingController::class, 'toggleSeat'])->name('seating.seats.toggle');
                Route::put('/events/{event:slug}/sitzplan/karte', [Organizer\SeatingController::class, 'saveMap'])->name('seating.map');

                Route::post('/events/{event:slug}/tickets', [Organizer\TicketTypeController::class, 'store'])->name('tickets.store');
                Route::put('/events/{event:slug}/tickets/{ticketType}', [Organizer\TicketTypeController::class, 'update'])->name('tickets.update');
                Route::delete('/events/{event:slug}/tickets/{ticketType}', [Organizer\TicketTypeController::class, 'destroy'])->name('tickets.destroy');

                $orders = Organizer\OrderController::class;
                Route::get('/events/{event:slug}/bestellungen', [$orders, 'index'])->name('orders.index');
                Route::get('/events/{event:slug}/bestellungen/export', [$orders, 'export'])->name('orders.export');
                Route::get('/events/{event:slug}/bestellungen/{order}', [$orders, 'show'])->name('orders.show');
                Route::post('/events/{event:slug}/bestellungen/{order}/bezahlt', [$orders, 'paid'])->name('orders.paid');
                Route::post('/events/{event:slug}/bestellungen/{order}/stornieren', [$orders, 'cancel'])->name('orders.cancel');
                Route::post('/events/{event:slug}/bestellungen/{order}/erstatten', [$orders, 'refund'])->name('orders.refund');
                Route::post('/events/{event:slug}/bestellungen/{order}/erneut-senden', [$orders, 'resend'])->name('orders.resend');

                Route::get('/gutscheine', [Organizer\VoucherController::class, 'index'])->name('vouchers.index');
                Route::post('/gutscheine', [Organizer\VoucherController::class, 'store'])->name('vouchers.store');
                Route::get('/gutscheine/{voucher}/bearbeiten', [Organizer\VoucherController::class, 'edit'])->name('vouchers.edit');
                Route::put('/gutscheine/{voucher}', [Organizer\VoucherController::class, 'update'])->name('vouchers.update');
                Route::delete('/gutscheine/{voucher}', [Organizer\VoucherController::class, 'destroy'])->name('vouchers.destroy');
            });
        });
    });
});
