@extends('layouts.app')
@section('title', 'Domains einrichten')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<h1 class="mb-2 text-2xl font-bold">Domains einrichten</h1>
<p class="mb-6 max-w-3xl text-sm text-slate-600">Anleitung für den Server-Betrieb der Reseller-Domains mit Selbsttest. Reseller richten ihre Domain selbst unter «Domains» im Reseller-Bereich ein und sehen dort ihre persönliche Anleitung.</p>

@php($badge = ['ok' => 'bg-emerald-100 text-emerald-800', 'warn' => 'bg-amber-100 text-amber-800', 'fail' => 'bg-red-100 text-red-800'])
@php($label = ['ok' => 'OK', 'warn' => 'Hinweis', 'fail' => 'Fehlt'])

<div class="card mb-6 max-w-3xl">
    <div class="mb-3 flex items-center justify-between"><h2 class="font-semibold">Selbsttest</h2><a href="{{ route('admin.domains.guide') }}" class="btn btn-ghost">Erneut prüfen</a></div>
    <ul class="divide-y divide-slate-100">
        @foreach ($checks as $c)
            <li class="flex gap-3 py-2.5 text-sm">
                <span class="mt-0.5 h-fit shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$c['state']] }}">{{ $label[$c['state']] }}</span>
                <div class="min-w-0"><div class="font-medium">{{ $c['label'] }}</div><div class="break-words text-slate-600">{{ $c['detail'] }}</div>
                    @if ($c['state'] !== 'ok' && $c['hint'])<div class="mt-0.5 text-xs text-slate-500">{{ $c['hint'] }}</div>@endif</div>
            </li>
        @endforeach
    </ul>
</div>

<div class="card mb-6 max-w-3xl space-y-5 text-sm">
    <h2 class="text-base font-semibold">Schritt für Schritt</h2>

    <section>
        <h3 class="font-medium">1. Ziel und Server-IP festlegen</h3>
        <p class="mt-1 text-slate-600">Alle Kunden-Domains zeigen per CNAME auf ein festes Ziel (z. B. <code>{{ $target ?: 'tickets.deine-domain.ch' }}</code>). Dieses Ziel braucht einen A-Eintrag auf die IP deines Servers.</p>
        @include('reseller._dns_table', ['records' => [['type' => 'A', 'name' => $target ?: 'tickets', 'value' => $ips[0] ?? 'IP-DEINES-SERVERS', 'ttl' => 300, 'note' => null, 'alternative' => false]]])
        <p class="mt-2 text-slate-600">In der <code>.env</code>:</p>
        <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">TICKETS_CNAME_TARGET={{ $target ?: 'tickets.deine-domain.ch' }}
SESSION_DOMAIN=</pre>
    </section>

    <section>
        <h3 class="font-medium">2. Optional: automatische Subdomains für Reseller</h3>
        <p class="mt-1 text-slate-600">Jeder Reseller erreichbar unter <code>{slug}.{{ $base ?: 'deine-domain.ch' }}</code>, ohne dass jemand DNS pflegen muss. Dafür ein Wildcard-Eintrag:</p>
        @include('reseller._dns_table', ['records' => [['type' => 'A', 'name' => '*.'.($base ?: 'deine-domain.ch'), 'value' => $ips[0] ?? 'IP-DEINES-SERVERS', 'ttl' => 300, 'note' => 'Alternativ ein CNAME auf '.($target ?: 'das Ziel').'.', 'alternative' => false]]])
        <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">TICKETS_BASE_DOMAIN={{ $base ?: 'deine-domain.ch' }}</pre>
        <p class="mt-2 text-xs text-slate-500">Nach Änderungen an der <code>.env</code>: <code>php artisan config:clear</code> (bzw. <code>config:cache</code>).</p>
    </section>

    <section>
        <h3 class="font-medium">3. Webserver und HTTPS</h3>
        <p class="mt-1 text-slate-600">Der Webserver muss <em>jede</em> Reseller-Domain auf dieselbe Installation leiten und dafür ein Zertifikat haben. Wähle das passende Setup:</p>

        <details class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50/40 p-3" open>
            <summary class="cursor-pointer font-medium">Plesk vollautomatisch (empfohlen: einmal einrichten, danach keine Handarbeit mehr)</summary>
            <p class="mt-2 text-slate-600">Sobald eine Reseller-Domain bestätigt ist, legt die Plattform in Plesk den Domain-Alias an und stellt ein Let's-Encrypt-Zertifikat aus. Das läuft im bestehenden Cron-Aufruf mit (alle 5 Minuten), du brauchst keinen zusätzlichen Cron. Plesk verlängert die Zertifikate selbst.</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-slate-600">
                <li>Einen API-Schlüssel erzeugen (per SSH als root, einmalig; die IP ist die, von der der Aufruf kommt, hier der Server selbst):
                    <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">plesk bin secret_key --create -ip-address 127.0.0.1 -description "Vylaris Tickets"</pre>
                    <span class="text-xs text-slate-500">Der Schlüssel gilt wie ein Admin-Passwort. Gib ihn nur in der <code>.env</code> an und nie weiter. Kommt der Aufruf nicht von 127.0.0.1, trage die Server-IP ein.</span></li>
                <li>In die <code>.env</code> eintragen:
                    <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">PLESK_API_KEY=der-erzeugte-schluessel
PLESK_DOMAIN={{ $host ?: 'events.deine-domain.ch' }}
PLESK_LE_EMAIL=du@deine-domain.ch
# PLESK_URL=https://127.0.0.1:8443</pre></li>
                <li>Voraussetzung: Die Let's-Encrypt-Erweiterung ist in Plesk installiert, und <code>PLESK_DOMAIN</code> ist genau der Name, unter dem die Installation in Plesk als Domain angelegt ist. Danach <code>php artisan config:clear</code> (oder im Selbsttest oben prüfen).</li>
            </ol>
            <p class="mt-2 text-xs text-slate-500">Das Zertifikat ist ein gemeinsames Zertifikat der Haupt-Domain und aller Reseller-Domains (bis 99 Domains). Fehler pro Domain stehen im Selbsttest.</p>
        </details>

        <details class="mt-2 rounded-lg border border-slate-200 p-3">
            <summary class="cursor-pointer font-medium">Plesk von Hand</summary>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-slate-600">
                <li>Für Wildcard-Subdomains: Bei der Hauptdomain eine Subdomain mit dem Namen <code>*</code> anlegen, mit demselben Dokumentenstamm wie die Installation (Menünamen unterscheiden sich je nach Plesk-Version).</li>
                <li>Pro Kunden-Domain: in der Domain-Verwaltung einen <strong>Domain-Alias</strong> auf die Installation anlegen und dort «Let's Encrypt» aktivieren. Das geht erst, wenn der DNS-Eintrag des Kunden stimmt.</li>
                <li>Ein Wildcard-Zertifikat für <code>*.{{ $base ?: 'deine-domain.ch' }}</code> verlangt DNS-Validierung (DNS-01). Das setzt voraus, dass Plesk die DNS-Zone der Domain verwaltet.</li>
            </ol>
        </details>

        <details class="mt-2 rounded-lg border border-slate-200 p-3">
            <summary class="cursor-pointer font-medium">Caddy (automatisch, empfohlen für viele Domains)</summary>
            <p class="mt-2 text-slate-600">Caddy holt Zertifikate bei Bedarf, aber nur für Domains, die die Plattform kennt (bestätigte Reseller-Domains):</p>
            @if (! $token)<p class="mt-1 text-xs text-amber-700">Setze zuerst <code>TICKETS_DOMAIN_CHECK_TOKEN</code> in der .env (langer Zufallswert, z. B. <code>openssl rand -hex 24</code>).</p>@endif
            <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">{
    on_demand_tls {
        ask {{ rtrim(config('app.url'), '/') }}/domain-check?token={{ $token ?: 'DEIN_TOKEN' }}
    }
}

https:// {
    tls {
        on_demand
    }
    root * /pfad/zu/vylaris-tickets/public
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}</pre>
        </details>

        <details class="mt-2 rounded-lg border border-slate-200 p-3">
            <summary class="cursor-pointer font-medium">nginx / Apache mit certbot</summary>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-600">
                <li>Einen Standard-<code>server</code>-Block (nginx: <code>server_name _;</code>) bzw. VirtualHost für die Installation anlegen, der alle Hostnamen annimmt.</li>
                <li>Pro Kunden-Domain: <code>certbot --nginx -d kunde.ch</code> (oder <code>--apache</code>), sobald der DNS-Eintrag stimmt.</li>
                <li>Für <code>*.{{ $base ?: 'deine-domain.ch' }}</code> ein Wildcard-Zertifikat per DNS-01 (<code>certbot --manual --preferred-challenges dns</code> oder ein DNS-Plugin deines Anbieters).</li>
            </ul>
        </details>
    </section>

    <section>
        <h3 class="font-medium">4. Ablauf für neue Reseller-Domains</h3>
        <ol class="mt-1 list-decimal space-y-1 pl-5 text-slate-600">
            <li>Reseller trägt die Domain unter «Domains» ein und legt den angezeigten DNS-Eintrag an.</li>
            <li>Die Plattform prüft alle 15 Minuten (oder per «Prüfen») und bestätigt die Domain automatisch. Nicht bestätigte Domains verfallen nach {{ \App\Models\ResellerDomain::PENDING_DAYS }} Tagen.</li>
            <li>Du aktivierst (je nach Webserver) Alias und Zertifikat. Bei Caddy geschieht das von selbst.</li>
        </ol>
    </section>
</div>

<div class="card max-w-3xl">
    <h2 class="mb-3 font-semibold">Reseller und ihre Domains</h2>
    @forelse ($resellers as $r)
        <div class="border-t border-slate-100 py-2.5 text-sm first:border-0">
            <div class="font-medium">{{ $r->name }} <span class="font-normal text-slate-500">· {{ $r->slug }}{{ $base ? '.'.$base : '' }}</span></div>
            @forelse ($r->domains as $d)
                <div class="text-slate-600"><code>{{ $d->domain }}</code> · @if ($d->isVerified())<span class="text-emerald-700">bestätigt</span>@else<span class="text-amber-700">wartet auf DNS</span>@endif</div>
            @empty
                <div class="text-xs text-slate-400">Keine eigene Domain</div>
            @endforelse
        </div>
    @empty
        <p class="text-sm text-slate-500">Noch keine Reseller.</p>
    @endforelse
</div>
@endsection
