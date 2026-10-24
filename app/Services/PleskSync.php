<?php

namespace App\Services;

use App\Models\ResellerDomain;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Richtet bestätigte Reseller-Domains automatisch in Plesk ein: Domain-Alias der Installation
 * und ein Let's-Encrypt-Zertifikat, das die Haupt-Domain und alle Aliase abdeckt.
 *
 * Läuft über die Plesk-REST-API (X-API-Key), also ohne Root-Rechte der Web-App. Die Verlängerung der
 * Zertifikate übernimmt Plesk selbst.
 */
class PleskSync
{
    /** Let's Encrypt erlaubt höchstens 100 Namen pro Zertifikat. */
    private const MAX_NAMES = 99;

    public function enabled(): bool
    {
        return config('tickets.plesk.key') && config('tickets.plesk.domain');
    }

    /** Legt fehlende Aliase an und stellt bei Änderungen das Zertifikat neu aus. Gibt [Aliase, Zertifikat neu?] zurück. */
    public function sync(): array
    {
        if (! $this->enabled()) {
            return [0, false];
        }

        $created = 0;

        ResellerDomain::whereNotNull('verified_at')->whereNull('plesk_alias_at')->orderBy('id')->get()->each(function (ResellerDomain $d) use (&$created) {
            $r = $this->cli('site_alias', ['--create', $d->domain, '-domain', config('tickets.plesk.domain'), '-web', 'true', '-mail', 'false']);
            // «existiert bereits» ist für uns Erfolg.
            if ($r['ok'] || str_contains(strtolower($r['out']), 'already exists') || str_contains(strtolower($r['out']), 'bereits')) {
                $d->forceFill(['plesk_alias_at' => now(), 'plesk_error' => null])->save();
                $created++;
            } else {
                $d->forceFill(['plesk_error' => mb_substr($r['out'], 0, 480)])->save();
            }
        });

        $renewed = false;
        $needsCert = ResellerDomain::whereNotNull('plesk_alias_at')->whereNull('plesk_cert_at')->exists();

        if ($needsCert) {
            $names = ResellerDomain::whereNotNull('plesk_alias_at')->orderBy('id')->limit(self::MAX_NAMES)->pluck('domain')->all();
            $args = ['--exec', 'letsencrypt', 'cli.php', '-d', config('tickets.plesk.domain')];
            foreach ($names as $name) {
                array_push($args, '-d', $name);
            }
            if ($email = config('tickets.plesk.email')) {
                array_push($args, '-m', $email);
            }

            $r = $this->cli('extension', $args);
            $ids = ResellerDomain::whereNotNull('plesk_alias_at')->whereNull('plesk_cert_at')->whereIn('domain', $names);
            if ($r['ok']) {
                $ids->update(['plesk_cert_at' => now(), 'plesk_error' => null]);
                $renewed = true;
            } else {
                $ids->update(['plesk_error' => mb_substr('Zertifikat: '.$r['out'], 0, 480)]);
            }
        }

        return [$created, $renewed];
    }

    /** Entfernt den Alias einer gelöschten Domain (best effort). */
    public function forget(ResellerDomain $d): void
    {
        if ($this->enabled() && $d->plesk_alias_at) {
            $this->cli('site_alias', ['--delete', $d->domain]);
        }
    }

    /** @return array{ok:bool,out:string} */
    private function cli(string $command, array $params): array
    {
        try {
            $res = Http::withHeaders(['X-API-Key' => (string) config('tickets.plesk.key')])
                ->withoutVerifying() // Plesk nutzt lokal ein selbst signiertes Zertifikat
                ->timeout(90)
                ->acceptJson()
                ->post(rtrim((string) config('tickets.plesk.url'), '/')."/api/v2/cli/{$command}/call", ['params' => $params]);
        } catch (Throwable $e) {
            return ['ok' => false, 'out' => 'Plesk nicht erreichbar: '.$e->getMessage()];
        }

        if (! $res->successful()) {
            return ['ok' => false, 'out' => 'Plesk-API antwortet mit '.$res->status().'.'];
        }

        $out = trim((string) $res->json('stdout').' '.(string) $res->json('stderr'));

        return ['ok' => (int) $res->json('code', 1) === 0, 'out' => $out];
    }
}
