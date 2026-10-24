<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Ordner, die Git nicht mitliefert oder die beim Entpacken verloren gehen, sonst Fehler 500 («valid cache path»).
foreach (['bootstrap/cache', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $dir) {
    if (! is_dir(dirname(__DIR__).'/'.$dir)) {
        @mkdir(dirname(__DIR__).'/'.$dir, 0775, true);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            \App\Http\Middleware\PrepareInstallation::class,
            \App\Http\Middleware\RequireInstallation::class,
        ], append: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);

        // Muss vor der Cookie-Verschlüsselung laufen (die Prioritätenliste sortiert sonst EncryptCookies nach vorn),
        // weil der APP_KEY vor der ersten Einrichtung noch fehlt.
        $middleware->prependToPriorityList(
            before: \Illuminate\Cookie\Middleware\EncryptCookies::class,
            prepend: \App\Http\Middleware\PrepareInstallation::class,
        );

        $middleware->validateCsrfTokens(except: ['webhooks/*', 'cron/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
