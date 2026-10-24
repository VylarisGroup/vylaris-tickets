<?php

$version = is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : '0.0.0';

return [
    'version' => $version,
    'name' => env('TICKETS_NAME', 'Vylaris Tickets'),
    'brand_color' => env('TICKETS_BRAND_COLOR') ?: '#1867F8',

    // Öffentliche Registrierung neuer Veranstalter auf der Hauptplattform.
    'allow_registration' => env('TICKETS_ALLOW_REGISTRATION', true),

    // Einrichtungsassistent beim ersten Start. Optionales Token (TICKETS_INSTALL_TOKEN) schützt ihn auf öffentlich erreichbaren Servern.
    'installer_enabled' => (bool) env('TICKETS_INSTALLER', true),
    'install_token' => env('TICKETS_INSTALL_TOKEN'),
    // Geheimer Teil der Cron-URL (/cron/{token}); wird automatisch erzeugt, wenn leer.
    'cron_token' => env('TICKETS_CRON_TOKEN'),

    // Web-Push (VAPID). Leer = Schlüssel werden beim ersten Bedarf erzeugt und in storage/app/vapid.json gespeichert.
    'composer' => env('TICKETS_COMPOSER'),

    'vapid_public' => env('VAPID_PUBLIC_KEY'),
    'vapid_private' => env('VAPID_PRIVATE_KEY'),

    'default_currency' => 'CHF',
    'currencies' => ['CHF', 'EUR'],

    // Kategorien für Suche und Filter in der öffentlichen Übersicht
    'categories' => [
        'konzert' => 'Konzert', 'party' => 'Party & Club', 'festival' => 'Festival', 'theater' => 'Theater & Kabarett',
        'kunst' => 'Kunst & Kultur', 'sport' => 'Sport', 'kurs' => 'Kurs & Workshop', 'vortrag' => 'Vortrag & Messe',
        'familie' => 'Familie & Kinder', 'sonstiges' => 'Sonstiges',
    ],
];
