<?php

namespace App\Services;

use App\Models\Reseller;
use Illuminate\Support\Str;

/**
 * Anleitung und Selbsttest für Reseller-Domains: berechnet die nötigen DNS-Einträge (dynamisch je Domain),
 * zeigt den aktuellen DNS-Stand und prüft die Server-Einrichtung (Basisdomain, Wildcard, Ziel).
 */
class DomainGuide
{
    /** Zweistufige Endungen, bei denen «meinverein.co.uk» eine Hauptdomain (Apex) ist. */
    private const SECOND_LEVEL = ['co.uk', 'org.uk', 'me.uk', 'com.au', 'net.au', 'org.au', 'co.nz', 'com.br', 'co.jp', 'co.za', 'com.tr', 'com.mx', 'co.in', 'com.cn'];

    public function __construct(private Dns $dns, private DomainVerifier $verifier) {}

    public function secondLevelSuffixes(): array
    {
        return self::SECOND_LEVEL;
    }

    public function target(): string
    {
        return $this->verifier->target();
    }

    /** IP-Adressen des Ziels (für A-Einträge). */
    public function targetIps(): array
    {
        return $this->dns->a($this->target());
    }

    /** Ist die Domain eine Hauptdomain (ohne Subdomain)? Dort ist kein CNAME möglich. */
    public function isApex(string $domain): bool
    {
        $domain = $this->verifier->normalize($domain);
        $labels = explode('.', $domain);

        foreach (self::SECOND_LEVEL as $suffix) {
            if (str_ends_with($domain, '.'.$suffix)) {
                return count($labels) === substr_count($suffix, '.') + 2;
            }
        }

        return count($labels) <= 2;
    }

    /** Der Teil vor der Hauptdomain: «tickets» bei tickets.meinverein.ch, «@» bei der Hauptdomain. */
    public function host(string $domain): string
    {
        $domain = $this->verifier->normalize($domain);
        if ($this->isApex($domain)) {
            return '@';
        }

        $suffixLabels = 2;
        foreach (self::SECOND_LEVEL as $suffix) {
            if (str_ends_with($domain, '.'.$suffix)) {
                $suffixLabels = substr_count($suffix, '.') + 2;
            }
        }

        return implode('.', array_slice(explode('.', $domain), 0, -$suffixLabels));
    }

    /**
     * Die DNS-Einträge, die der Kunde setzen muss.
     *
     * @return array<int,array{type:string,name:string,value:string,ttl:int,note:?string,alternative:bool}>
     */
    public function records(string $domain): array
    {
        $domain = $this->verifier->normalize($domain);
        $target = $this->target();

        if (! $this->isApex($domain)) {
            return [['type' => 'CNAME', 'name' => $this->host($domain), 'value' => $target, 'ttl' => 300, 'note' => null, 'alternative' => false]];
        }

        $records = [['type' => 'ALIAS / ANAME', 'name' => '@', 'value' => $target, 'ttl' => 300, 'note' => 'Wenn dein Anbieter ALIAS/ANAME/«CNAME-Flattening» anbietet (z. B. Cloudflare, Infomaniak).', 'alternative' => false]];
        foreach ($this->targetIps() as $ip) {
            $records[] = ['type' => 'A', 'name' => '@', 'value' => $ip, 'ttl' => 300, 'note' => 'Sonst: A-Eintrag mit der IP-Adresse der Plattform.', 'alternative' => true];
        }

        return $records;
    }

    /**
     * Aktueller DNS-Stand einer Domain.
     *
     * @return array{cname:array,a:array,ok:bool,message:string}
     */
    public function status(string $domain): array
    {
        $domain = $this->verifier->normalize($domain);
        $target = $this->target();
        $cname = $this->dns->cname($domain);
        $a = $this->dns->a($domain);
        $ours = $this->targetIps();

        $ok = in_array($target, $cname, true) || ($a !== [] && array_intersect($a, $ours) !== []);
        $message = match (true) {
            $ok => 'DNS zeigt korrekt auf die Plattform.',
            $cname !== [] => 'CNAME zeigt auf «'.implode(', ', $cname).'», erwartet: «'.$target.'».',
            $a !== [] => 'A-Eintrag zeigt auf '.implode(', ', $a).', erwartet: '.($ours ? implode(', ', $ours) : $target).'.',
            default => 'Noch kein DNS-Eintrag gefunden (Änderungen brauchen einige Minuten bis Stunden).',
        };

        return ['cname' => $cname, 'a' => $a, 'ok' => $ok, 'message' => $message];
    }

    /**
     * Selbsttest der Server-Einrichtung für die Administration.
     *
     * @return array<int,array{label:string,state:string,detail:string,hint:?string}> state: ok | warn | fail
     */
    public function selfTest(): array
    {
        $checks = [];
        $add = function (string $label, string $state, string $detail, ?string $hint = null) use (&$checks) {
            $checks[] = ['label' => $label, 'state' => $state, 'detail' => $detail, 'hint' => $hint];
        };

        $base = strtolower((string) config('tickets.base_domain'));
        $target = $this->target();
        $ips = $this->targetIps();
        $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        $add('APP_URL', str_starts_with((string) config('app.url'), 'https://') ? 'ok' : 'warn', (string) config('app.url'), 'APP_URL sollte mit https:// beginnen.');

        $add('CNAME-Ziel (TICKETS_CNAME_TARGET)', $target !== '' ? 'ok' : 'fail', $target !== '' ? $target : 'nicht gesetzt', 'Setze TICKETS_CNAME_TARGET oder TICKETS_BASE_DOMAIN in der .env.');

        $add('Ziel zeigt per DNS auf diesen Server', $ips !== [] ? 'ok' : 'fail', $ips !== [] ? 'A: '.implode(', ', $ips) : 'Das Ziel «'.$target.'» hat keinen A-Eintrag.', 'Lege beim DNS-Anbieter einen A-Eintrag für '.$target.' auf die Server-IP an.');

        if ($base === '') {
            $add('Subdomains für Reseller (TICKETS_BASE_DOMAIN)', 'warn', 'nicht gesetzt: Reseller bekommen keine automatische Subdomain', 'Optional: TICKETS_BASE_DOMAIN setzen und Wildcard-DNS (*.'.($appHost ?: 'deine-domain').') anlegen.');
        } else {
            $add('Basisdomain (TICKETS_BASE_DOMAIN)', 'ok', $base);
            $probe = 'dns-test-'.Str::lower(Str::random(8)).'.'.$base;
            $wild = array_merge($this->dns->a($probe), $this->dns->cname($probe));
            $wildOk = $wild !== [] && (array_intersect($this->dns->a($probe), $ips) !== [] || in_array($target, $this->dns->cname($probe), true));
            $add('Wildcard-DNS (*.'.$base.')', $wildOk ? 'ok' : 'fail', $wildOk ? 'Zufällige Subdomain löst auf diesen Server auf.' : 'Eine zufällige Subdomain ('.$probe.') löst nicht auf diesen Server auf.', 'Lege einen Wildcard-Eintrag an: Typ A (oder CNAME) · Name *.'.$base.' (bzw. * in der Zone) · Wert '.($ips[0] ?? $target).'.');
        }

        $token = (string) config('tickets.domain_check_token');
        $add('Zertifikats-Prüfung (TICKETS_DOMAIN_CHECK_TOKEN)', $token !== '' ? 'ok' : 'warn', $token !== '' ? 'gesetzt' : 'nicht gesetzt (nur nötig für Caddy on-demand TLS)', 'Optional: ein langes Zufalls-Token setzen und in der Caddy-Konfiguration verwenden.');

        $sessionDomain = config('session.domain');
        $add('Sitzungs-Cookie (SESSION_DOMAIN)', $sessionDomain ? 'warn' : 'ok', $sessionDomain ? (string) $sessionDomain : 'leer (richtig: Cookie gilt pro Domain)', 'Lass SESSION_DOMAIN leer, sonst funktioniert der Login auf Reseller-Domains nicht.');

        return $checks;
    }

    /** Alle bestätigten Domains aller Reseller (für die Übersicht in der Administration). */
    public function overview()
    {
        return Reseller::with('domains')->orderBy('name')->get();
    }
}
