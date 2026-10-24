<?php

$version = is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : '0.0.0';

return [
    'version' => $version,
    'name' => env('TICKETS_NAME', 'Vylaris Tickets'),
    'brand_color' => env('TICKETS_BRAND_COLOR', '#1867F8'),

    // Basisdomain für Reseller-Subdomains, z. B. tickets.vylaris.ch -> {slug}.tickets.vylaris.ch
    'base_domain' => env('TICKETS_BASE_DOMAIN'),

    // Öffentliche Registrierung neuer Veranstalter auf der Hauptplattform.
    'allow_registration' => env('TICKETS_ALLOW_REGISTRATION', true),

    'default_currency' => 'CHF',
    'currencies' => ['CHF', 'EUR'],
];
