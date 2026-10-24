@extends('layouts.app')
@section('title', 'Domains')
@section('subnav') @include('reseller._subnav') @endsection
@section('content')
<h1 class="mb-2 text-2xl font-bold">Eigene Domains</h1>
<p class="mb-6 max-w-2xl text-sm text-slate-600">Deine Veranstalter und Kunden erreichen die Plattform unter deiner eigenen Adresse, mit deinem Branding. Richte dafür bei deinem Domain-Anbieter einen CNAME-Eintrag ein.</p>

<div class="mb-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-4 text-sm">
    <div class="mb-1 font-semibold">So geht's</div>
    <ol class="list-decimal space-y-1 pl-5 text-slate-700">
        <li>Domain unten hinzufügen, z. B. <code>tickets.meinverein.ch</code>.</li>
        <li>Beim Domain-Anbieter einen <strong>CNAME</strong>-Eintrag anlegen: Name <code>tickets</code> (bzw. die Subdomain), Ziel <code class="select-all">{{ $target }}</code>.</li>
        <li>Auf «Prüfen» klicken. DNS-Änderungen brauchen manchmal bis zu einigen Stunden. Wir prüfen automatisch alle 15 Minuten.</li>
    </ol>
    <p class="mt-2 text-xs text-slate-500">Hauptdomains ohne Subdomain (z. B. <code>meinverein.ch</code>) erlauben meist keinen CNAME. Nutze dort einen ALIAS/ANAME-Eintrag auf dasselbe Ziel oder einen A-Eintrag mit derselben IP-Adresse.</p>
</div>

@error('domain')<div class="mb-4 max-w-2xl rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

<div class="card mb-6 max-w-2xl divide-y divide-slate-100 p-0">
    @forelse ($domains as $d)
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
            <div>
                <div class="font-medium">{{ $d->domain }}</div>
                @if ($d->isVerified())
                    <div class="text-sm text-emerald-700">Aktiv seit {{ $d->verified_at->format('d.m.Y') }}</div>
                @else
                    <div class="text-sm text-amber-700">Wartet auf CNAME → <code>{{ $target }}</code></div>
                    @if ($d->last_error)<div class="mt-1 max-w-md text-xs text-slate-500">{{ $d->last_error }}</div>@endif
                @endif
            </div>
            <div class="flex gap-2">
                @unless ($d->isVerified())
                    <form method="POST" action="{{ route('reseller.domains.verify', [$reseller, $d]) }}">@csrf<button class="btn btn-primary">Prüfen</button></form>
                @endunless
                <form method="POST" action="{{ route('reseller.domains.destroy', [$reseller, $d]) }}" onsubmit="return confirm('Domain entfernen? Sie zeigt danach nicht mehr auf deine Plattform.')">@csrf @method('DELETE')<button class="btn btn-danger">Entfernen</button></form>
            </div>
        </div>
    @empty
        <div class="px-6 py-4 text-sm text-slate-500">Noch keine eigene Domain.</div>
    @endforelse
</div>

@if ($reseller->domain)
    <p class="mb-6 max-w-2xl text-sm text-slate-500">Vom Plattform-Administrator eingerichtet: <strong>{{ $reseller->domain }}</strong></p>
@endif

@if ($domains->count() < $limit)
<form method="POST" action="{{ route('reseller.domains.store', $reseller) }}" class="card max-w-2xl space-y-3">@csrf
    <x-field name="domain" label="Domain hinzufügen" placeholder="tickets.meinverein.ch" required />
    <button class="btn btn-primary">Hinzufügen und prüfen</button>
</form>
@else
    <p class="max-w-2xl text-sm text-slate-500">Limit von {{ $limit }} Domains erreicht.</p>
@endif
@endsection
