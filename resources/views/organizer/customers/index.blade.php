@extends('layouts.app')
@section('title', 'Kunden')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight">Kunden</h1>
        <p class="text-sm text-slate-500">Alle Käufer mit bezahlter Bestellung, zusammengefasst nach E-Mail-Adresse.</p>
    </div>
    <a href="{{ route('organizer.customers.export', [$organizer, 'q' => $q ?: null]) }}" class="btn btn-ghost"><x-icon name="download" :size="16" />CSV exportieren</a>
</div>
<form method="GET" class="mb-4 flex gap-2"><input name="q" value="{{ $q }}" class="input !w-72" placeholder="Name oder E-Mail" aria-label="Suche"><button class="btn btn-ghost">Suchen</button>@if ($q)<a href="{{ route('organizer.customers.index', $organizer) }}" class="self-center text-sm text-slate-500 hover:text-brand">Zurücksetzen</a>@endif</form>
<div class="card overflow-x-auto p-0">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 text-slate-500"><tr><th class="px-6 py-3">Name</th><th class="px-6 py-3">E-Mail</th><th class="px-6 py-3 text-right">Bestellungen</th><th class="px-6 py-3 text-right">Tickets</th><th class="px-6 py-3 text-right">Umsatz</th><th class="px-6 py-3">Zuletzt</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($customers as $c)
            <tr>
                <td class="px-6 py-3 font-medium">{{ $c->name }}</td>
                <td class="px-6 py-3"><a class="text-brand" href="mailto:{{ $c->email }}">{{ $c->email }}</a></td>
                <td class="px-6 py-3 text-right">{{ $c->orders_count }}</td>
                <td class="px-6 py-3 text-right">{{ $c->tickets_count }}</td>
                <td class="px-6 py-3 text-right">{{ number_format($c->revenue_cents / 100, 2, '.', '’') }}</td>
                <td class="px-6 py-3 text-slate-500">{{ $c->last_at ? \Illuminate\Support\Carbon::parse($c->last_at)->format('d.m.Y') : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-6 py-4 text-slate-500">Noch keine Kunden.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $customers->links() }}</div>
<p class="mt-3 text-xs text-slate-500">Umsatz in den jeweiligen Bestellwährungen zusammengezählt. Bei gemischten Währungen bitte den CSV-Export prüfen.</p>
@endsection
