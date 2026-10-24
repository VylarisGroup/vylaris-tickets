<?php

$version = is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : '0.0.0';

return [
    'version' => $version,
    'name' => env('TICKETS_NAME', 'Vylaris Tickets'),
    'brand_color' => env('TICKETS_BRAND_COLOR') ?: '#1867F8',

    // Basisdomain für Reseller-Subdomains, z. B. tickets.vylaris.ch -> {slug}.tickets.vylaris.ch
    'base_domain' => env('TICKETS_BASE_DOMAIN'),

    // Öffentliche Registrierung neuer Veranstalter auf der Hauptplattform.
    'allow_registration' => env('TICKETS_ALLOW_REGISTRATION', true),

    // Ziel, auf das Reseller ihre eigene Domain per CNAME legen (Standard: Basisdomain bzw. Host von APP_URL).
    'cname_target' => env('TICKETS_CNAME_TARGET'),
    'max_domains_per_reseller' => (int) env('TICKETS_MAX_DOMAINS', 5),
    // Optionales Token für /domain-check (z. B. für Caddy «on_demand_tls ask»).
    'domain_check_token' => env('TICKETS_DOMAIN_CHECK_TOKEN'),

    'default_currency' => 'CHF',
    'currencies' => ['CHF', 'EUR'],
];
