@extends('layouts.app')
@section('title', 'Domains')
@section('subnav') @include('reseller._subnav') @endsection
@section('content')
<h1 class="mb-2 text-2xl font-bold">Eigene Domains</h1>
<p class="mb-6 max-w-3xl text-sm text-slate-600">Deine Veranstalter und Kunden erreichen die Plattform unter deiner eigenen Adresse, mit deinem Branding. Richte dafür bei deinem Domain-Anbieter einen CNAME-Eintrag ein.</p>

<div class="card mb-6 max-w-3xl space-y-4 text-sm" x-data="{
    domain: '', target: @js($target), ips: @js($ips), suffixes: @js($guide->secondLevelSuffixes()),
    get d() { return this.domain.toLowerCase().trim().replace(/^[a-z]+:\/\//, '').split('/')[0].replace(/\.$/, ''); },
    get valid() { return /^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/.test(this.d); },
    get reg() { const s = this.suffixes.find((x) => this.d.endsWith('.' + x)); return s ? s.split('.').length + 1 : 2; },
    get labels() { return this.d.split('.'); },
    get apex() { return this.labels.length <= this.reg; },
    get host() { return this.apex ? '@' : this.labels.slice(0, -this.reg).join('.'); },
    get records() {
        if (!this.apex) return [{ type: 'CNAME', name: this.host, value: this.target, ttl: 300, alt: false }];
        return [{ type: 'ALIAS / ANAME', name: '@', value: this.target, ttl: 300, alt: false }, ...this.ips.map((ip) => ({ type: 'A', name: '@', value: ip, ttl: 300, alt: true }))];
    },
}">
    <div>
        <div class="text-base font-semibold">So richtest du deine Domain ein</div>
        <p class="mt-1 text-slate-600">Du brauchst eine Domain bzw. Subdomain, die dir gehört (z. B. <code>tickets.meinverein.ch</code>), und Zugriff auf deren DNS-Einstellungen beim Domain-Anbieter. Das Zertifikat (https) stellt die Plattform automatisch aus, sobald die Domain bestätigt ist.</p>
    </div>

    <ol class="space-y-3">
        <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">1</span>
            <div class="min-w-0 flex-1">
                <div class="font-medium">Gib hier deine gewünschte Adresse ein und sieh sofort, welcher DNS-Eintrag nötig ist</div>
                <input x-model="domain" placeholder="tickets.meinverein.ch" class="input mt-2 max-w-md" aria-label="Gewünschte Domain" autocomplete="off">
                <div x-show="d && !valid" x-cloak class="mt-2 text-xs text-amber-700">Bitte eine gültige Domain eingeben, z. B. tickets.meinverein.ch.</div>
                <div x-show="valid" x-cloak class="mt-3 space-y-2">
                    <div class="text-xs text-slate-600" x-show="apex">Das ist eine Hauptdomain ohne Subdomain. Dort ist ein normaler CNAME nicht erlaubt. Empfehlung: lieber eine Subdomain wie <code>tickets.<span x-text="d"></span></code> verwenden.</div>
                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-3 py-2">Typ</th><th class="px-3 py-2">Name / Host</th><th class="px-3 py-2">Wert / Ziel</th><th class="px-3 py-2">TTL</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="r in records" :key="r.type + r.value">
                                    <tr><td class="px-3 py-2 font-mono font-semibold"><span x-text="r.type"></span><span x-show="r.alt" class="ml-1 text-xs font-normal text-slate-500">(Alternative)</span></td>
                                        <td class="px-3 py-2 font-mono"><span class="select-all" x-text="r.name"></span></td>
                                        <td class="px-3 py-2 font-mono"><span class="select-all break-all" x-text="r.value"></span></td>
                                        <td class="px-3 py-2 font-mono" x-text="r.ttl"></td></tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-slate-500" x-show="!apex">«Name / Host» ist nur der Teil vor deiner Hauptdomain. Bei manchen Anbietern musst du stattdessen den vollen Namen <code x-text="d"></code> eintragen.</p>
                </div>
            </div>
        </li>
        <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">2</span>
            <div class="flex-1"><div class="font-medium">Eintrag beim Domain-Anbieter anlegen</div>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-slate-600">
                    <li>Beim Anbieter in die <strong>DNS-Verwaltung</strong> der Domain (oft «DNS», «Zone» oder «Nameserver-Einträge») gehen und einen neuen Eintrag mit den Werten oben hinzufügen.</li>
                    <li>Gibt es für denselben Namen schon einen <strong>A-, AAAA- oder anderen CNAME-Eintrag</strong>, diesen löschen. Ein CNAME darf nicht neben anderen Einträgen mit demselben Namen stehen.</li>
                    <li>Bei <strong>Cloudflare</strong> den Eintrag auf «Nur DNS» (graue Wolke) stellen, sonst kann die Domain nicht bestätigt werden.</li>
                    <li>Das Ziel ohne <code>https://</code> und ohne Schrägstrich eintragen. Einen abschliessenden Punkt (<code>{{ $target }}.</code>) verlangen nur manche Anbieter.</li>
                </ul></div>
        </li>
        <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">3</span>
            <div class="flex-1"><div class="font-medium">Domain unten hinzufügen und «Prüfen» klicken</div>
                <p class="mt-1 text-slate-600">DNS-Änderungen brauchen meist 5 bis 30 Minuten, selten bis zu 24 Stunden. Wir prüfen wartende Domains automatisch alle 15 Minuten. Nach der Bestätigung ist die Domain sofort aktiv. Nicht bestätigte Domains werden nach {{ \App\Models\ResellerDomain::PENDING_DAYS }} Tagen entfernt.</p></div>
        </li>
    </ol>
    <p class="text-xs text-slate-500">Plattform-Ziel: <code class="select-all">{{ $target }}</code>@if ($ips) · IP-Adresse: <code class="select-all">{{ implode(', ', $ips) }}</code>@endif</p>

    <details class="rounded-lg border border-slate-200 p-3">
        <summary class="cursor-pointer font-medium">Hilfe bei Problemen</summary>
        <dl class="mt-2 space-y-2 text-slate-600">
            <div><dt class="font-medium text-slate-800">«Noch kein CNAME-Eintrag gefunden»</dt><dd>Der Eintrag ist noch nicht im DNS sichtbar. Warte 15 bis 30 Minuten und klicke erneut auf «Prüfen». Kontrolliere, dass Name und Ziel genau stimmen (kein Leerzeichen, kein <code>https://</code>).</dd></div>
            <div><dt class="font-medium text-slate-800">«Der CNAME zeigt auf …»</dt><dd>Es gibt einen Eintrag, aber mit dem falschen Ziel. Ändere den Wert auf <code>{{ $target }}</code>.</dd></div>
            <div><dt class="font-medium text-slate-800">Die Domain ist bestätigt, aber der Browser warnt vor dem Zertifikat</dt><dd>Das https-Zertifikat wird beim ersten Aufruf ausgestellt, das kann eine Minute dauern. Hilft ein erneutes Laden nach einigen Minuten nicht, melde dich beim Plattform-Betreiber.</dd></div>
            <div><dt class="font-medium text-slate-800">Wo finde ich die DNS-Einstellungen?</dt><dd>Dort, wo du die Domain gekauft hast (z. B. Infomaniak, Hostpoint, Cloudflare, GoDaddy, IONOS). Frage notfalls die Person, die deine Domain verwaltet. Die Anleitung dieses Anbieters für «CNAME-Eintrag hinzufügen» zeigt die genauen Felder.</dd></div>
        </dl>
    </details>
</div>

@error('domain')<div class="mb-4 max-w-3xl rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

<div class="card mb-6 max-w-3xl divide-y divide-slate-100 p-0">
    @forelse ($domains as $d)
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
            <div>
                <div class="font-medium">{{ $d->domain }}</div>
                @if ($d->isVerified())
                    <div class="text-sm text-emerald-700">Aktiv seit {{ $d->verified_at->format('d.m.Y') }}</div>
                @else
                    <div class="text-sm text-amber-700">Wartet auf DNS-Eintrag</div>
                    <div class="mt-1 max-w-md text-xs {{ $pending[$d->id]['status']['ok'] ? 'text-emerald-700' : 'text-slate-500' }}">Aktueller DNS-Stand: {{ $pending[$d->id]['status']['message'] }}@if ($pending[$d->id]['status']['ok']) Klicke auf «Prüfen».@endif</div>
                @endif
            </div>
            <div class="flex gap-2">
                @unless ($d->isVerified())
                    <form method="POST" action="{{ route('reseller.domains.verify', [$reseller, $d]) }}">@csrf<button class="btn btn-primary">Prüfen</button></form>
                @endunless
                <form method="POST" action="{{ route('reseller.domains.destroy', [$reseller, $d]) }}" onsubmit="return confirm('Domain entfernen? Sie zeigt danach nicht mehr auf deine Plattform.')">@csrf @method('DELETE')<button class="btn btn-danger">Entfernen</button></form>
            </div>
        </div>
        @unless ($d->isVerified())
            <div class="bg-slate-50/50 px-6 pb-4 pt-0"><details><summary class="cursor-pointer py-2 text-sm text-brand">Benötigte DNS-Einträge für {{ $d->domain }}</summary>@include('reseller._dns_table', ['records' => $pending[$d->id]['records']])</details></div>
        @endunless
    @empty
        <div class="px-6 py-4 text-sm text-slate-500">Noch keine eigene Domain.</div>
    @endforelse
</div>

@if ($reseller->domain)
    <p class="mb-6 max-w-3xl text-sm text-slate-500">Vom Plattform-Administrator eingerichtet: <strong>{{ $reseller->domain }}</strong></p>
@endif

@if ($domains->count() < $limit)
<form method="POST" action="{{ route('reseller.domains.store', $reseller) }}" class="card max-w-3xl space-y-3">@csrf
    <x-field name="domain" label="Domain hinzufügen" placeholder="tickets.meinverein.ch" required />
    <button class="btn btn-primary">Hinzufügen und prüfen</button>
</form>
@else
    <p class="max-w-3xl text-sm text-slate-500">Limit von {{ $limit }} Domains erreicht.</p>
@endif
@endsection
