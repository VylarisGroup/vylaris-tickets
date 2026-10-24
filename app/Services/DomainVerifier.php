<?php

namespace App\Services;

use App\Models\Reseller;
use App\Models\ResellerDomain;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/**
 * Eigene Domains von Resellern: Normalisierung, Regeln und DNS-Prüfung.
 *
 * Eine Domain gilt als bestätigt, wenn sie per CNAME auf das Ziel dieser Installation zeigt. Nur wer den
 * DNS-Eintrag der Domain ändern kann, kann das erreichen. Für Hauptdomains ohne CNAME-Möglichkeit (Apex)
 * genügt ein A-Eintrag auf dieselbe IP wie das Ziel (ALIAS/ANAME bzw. feste IP).
 */
class DomainVerifier
{
    public function __construct(private Dns $dns) {}

    /** Ziel, auf das der CNAME zeigen muss. */
    public function target(): string
    {
        $target = config('tickets.cname_target') ?: config('tickets.base_domain') ?: parse_url((string) config('app.url'), PHP_URL_HOST);

        return strtolower((string) $target);
    }

    /** Normalisiert Eingaben wie «https://Tickets.Beispiel.ch/» zu «tickets.beispiel.ch». */
    public function normalize(string $input): string
    {
        $input = strtolower(trim($input));
        $input = preg_replace('#^[a-z]+://#', '', $input);
        $input = explode('/', $input)[0];

        return rtrim($input, '.');
    }

    /** @throws InvalidArgumentException mit verständlicher Meldung */
    public function assertAllowed(Reseller $reseller, string $domain): void
    {
        if (! preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain) || strlen($domain) > 190) {
            throw new InvalidArgumentException('Bitte eine gültige Domain eingeben, z. B. tickets.meinverein.ch.');
        }

        $own = array_filter([$this->target(), strtolower((string) config('tickets.base_domain')), parse_url((string) config('app.url'), PHP_URL_HOST)]);
        foreach ($own as $host) {
            if ($domain === $host || str_ends_with($domain, '.'.$host)) {
                throw new InvalidArgumentException('Diese Domain gehört zur Plattform und kann nicht registriert werden.');
            }
        }

        if (ResellerDomain::where('domain', $domain)->whereNotNull('verified_at')->exists() || Reseller::where('domain', $domain)->exists()) {
            throw new InvalidArgumentException('Diese Domain ist bereits bei einem anderen Reseller registriert.');
        }

        if ($reseller->domains()->where('domain', $domain)->exists()) {
            throw new InvalidArgumentException('Diese Domain hast du bereits hinzugefügt.');
        }

        if ($reseller->domains()->count() >= (int) config('tickets.max_domains_per_reseller')) {
            throw new InvalidArgumentException('Das Limit von '.config('tickets.max_domains_per_reseller').' Domains ist erreicht.');
        }
    }

    public function add(Reseller $reseller, string $input): ResellerDomain
    {
        $domain = $this->normalize($input);
        $this->assertAllowed($reseller, $domain);

        return $reseller->domains()->create(['domain' => $domain]);
    }

    /** Prüft das DNS und speichert das Ergebnis. */
    public function verify(ResellerDomain $record): bool
    {
        $record->last_checked_at = now();
        $target = $this->target();

        $cnames = $this->dns->cname($record->domain);
        $ok = in_array($target, $cnames, true);

        if (! $ok) {
            // Apex-Domain: kein CNAME möglich, A-Eintrag muss auf dieselbe Adresse zeigen wie das Ziel.
            $mine = $this->dns->a($record->domain);
            $ours = $this->dns->a($target);
            $ok = $mine !== [] && array_intersect($mine, $ours) !== [];
        }

        if (! $ok) {
            $record->last_error = $cnames !== []
                ? 'Der CNAME zeigt auf «'.implode(', ', $cnames).'», erwartet wird «'.$target.'».'
                : 'Es wurde noch kein CNAME-Eintrag auf «'.$target.'» gefunden. DNS-Änderungen können einige Zeit dauern.';
            $record->save();

            return false;
        }

        try {
            $record->forceFill(['verified_at' => $record->verified_at ?? now(), 'verified_key' => $record->domain, 'last_error' => null])->save();
        } catch (QueryException) {
            // Ein anderer Reseller hat die Domain in der Zwischenzeit bestätigt.
            $record->forceFill(['verified_at' => null, 'verified_key' => null, 'last_error' => 'Diese Domain ist bereits bei einem anderen Reseller bestätigt.'])->save();

            return false;
        }

        return true;
    }

    /** Wartende Domains prüfen und zu alte entfernen. Gibt [bestätigt, entfernt] zurück. */
    public function sweep(): array
    {
        $confirmed = 0;

        ResellerDomain::whereNull('verified_at')->get()->each(function (ResellerDomain $d) use (&$confirmed) {
            if ($this->verify($d)) {
                $confirmed++;
            }
        });

        $removed = ResellerDomain::whereNull('verified_at')->where('created_at', '<', now()->subDays(ResellerDomain::PENDING_DAYS))->delete();

        return [$confirmed, $removed];
    }
}
