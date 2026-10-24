@extends('layouts.app')
@section('title', $organizer->name)
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">{{ $organizer->name }}</h1>
@unless ($organizer->is_active)
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Dieser Veranstalter ist gesperrt.</div>
@endunless
<div class="grid gap-4 sm:grid-cols-3">
    <div class="card"><div class="text-sm text-slate-500">Teammitglieder</div><div class="text-3xl font-bold">{{ $memberCount }}</div></div>
    <div class="card"><div class="text-sm text-slate-500">Währung</div><div class="text-3xl font-bold">{{ $organizer->default_currency }}</div></div>
    <div class="card"><div class="text-sm text-slate-500">MWST</div><div class="text-3xl font-bold">{{ $organizer->vat_enabled ? $organizer->vat_rate.' %' : 'aus' }}</div></div>
</div>
<div class="mt-4 grid gap-4 sm:grid-cols-2">
    <div class="card"><div class="text-sm text-slate-500">Umsatz (bezahlt)</div><div class="text-3xl font-bold">{{ \App\Support\Money::format($revenue, $organizer->default_currency) }}</div></div>
    <div class="card"><div class="text-sm text-slate-500">Verkaufte Tickets</div><div class="text-3xl font-bold">{{ $ticketsSold }}</div></div>
</div>
<div class="mt-6 flex flex-wrap gap-3">
    @can('sell-organizer', $organizer)<a href="{{ route('organizer.door.index', $organizer) }}" class="btn btn-primary">Abendkasse</a>@endcan
    @can('scan-organizer', $organizer)<a href="{{ route('organizer.scan.index', $organizer) }}" class="btn btn-primary">Einlass</a>@endcan
</div>
<h2 class="mb-3 mt-8 font-semibold">Anstehende Events</h2>
<div class="card divide-y divide-slate-100 p-0">
    @forelse ($upcoming as $e)
        @can('manage-organizer', $organizer)
            <a href="{{ route('organizer.events.show', [$organizer, $e]) }}" class="flex items-center justify-between px-6 py-3 hover:bg-slate-50"><span class="font-medium">{{ $e->title }}</span><span class="text-sm text-slate-500">{{ $e->starts_at->format('d.m.Y H:i') }}</span></a>
        @else
            <div class="flex items-center justify-between px-6 py-3"><span class="font-medium">{{ $e->title }}</span><span class="text-sm text-slate-500">{{ $e->starts_at->format('d.m.Y H:i') }}</span></div>
        @endcan
    @empty
        <div class="px-6 py-4 text-sm text-slate-500">Keine anstehenden Events.</div>
    @endforelse
</div>
@endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">{{ $organizer->name }}</h1>
@unless ($organizer->is_active)
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Dieser Veranstalter ist gesperrt.</div>
@endunless
<div class="grid gap-4 sm:grid-cols-3">
    <div class="card"><div class="text-sm text-slate-500">Teammitglieder</div><div class="text-3xl font-bold">{{ $memberCount }}</div></div>
    <div class="card"><div class="text-sm text-slate-500">Währung</div><div class="text-3xl font-bold">{{ $organizer->default_currency }}</div></div>
    <div class="card"><div class="text-sm text-slate-500">MWST</div><div class="text-3xl font-bold">{{ $organizer->vat_enabled ? $organizer->vat_rate.' %' : 'aus' }}</div></div>
</div>
<div class="card mt-6 text-sm text-slate-600">Events, Ticketverkauf, Abendkasse und Einlass folgen in den nächsten Ausbaustufen.</div>
@endsection
