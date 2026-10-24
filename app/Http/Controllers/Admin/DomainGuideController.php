<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DomainGuide;

/** Anleitung und Selbsttest für die Domain-Einrichtung der Plattform. */
class DomainGuideController extends Controller
{
    public function __invoke(DomainGuide $guide)
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return view('admin.domain-guide', [
            'checks' => $guide->selfTest(),
            'target' => $guide->target(),
            'ips' => $guide->targetIps(),
            'base' => strtolower((string) config('tickets.base_domain')),
            'token' => (string) config('tickets.domain_check_token'),
            'host' => $host,
            'resellers' => $guide->overview(),
        ]);
    }
}
