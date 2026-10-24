@extends('layouts.app')
@section('title', 'Statistik')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
@php
    $cur = $event?->currency ?? $organizer->default_currency;
    $money = fn ($c) => \App\Support\Money::format((int) $c, $cur);
    $maxRev = max(1, collect($series)->max('revenue'));
    $maxTickets = max(1, collect($series)->max('tickets'));
    $typeMax = max(1, $byType->max('count') ?? 1);
    $rate = $totals['tickets'] ? round($totals['checked_in'] / $totals['tickets'] * 100) : 0;
@endphp
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight">Statistik</h1>
        <p class="text-sm text-slate-500">{{ $event ? $event->title : 'Alle Events' }} · bezahlte Bestellungen</p>
    </div>
    <form method="GET" class="flex flex-wrap gap-2">
        <select name="event" class="input !w-56" onchange="this.form.submit()" aria-label="Event">
            <option value="">Alle Events</option>
            @foreach ($events as $e)<option value="{{ $e->slug }}" @selected($event?->id === $e->id)>{{ $e->title }}</option>@endforeach
        </select>
        <select name="tage" class="input !w-36" onchange="this.form.submit()" aria-label="Zeitraum">
            @foreach ([7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage', 365 => '1 Jahr'] as $d => $l)<option value="{{ $d }}" @selected($days === $d)>{{ $l }}</option>@endforeach
        </select>
        <noscript><button class="btn btn-ghost">Anwenden</button></noscript>
    </form>
</div>

<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="card"><div class="text-sm text-slate-500">Umsatz</div><div class="mt-1 text-2xl font-extrabold tracking-tight">{{ $money($totals['revenue']) }}</div><div class="text-xs text-slate-500">Ø {{ $money($totals['avg']) }} pro Bestellung</div></div>
    <div class="card"><div class="text-sm text-slate-500">Verkaufte Tickets</div><div class="mt-1 text-2xl font-extrabold tracking-tight">{{ $totals['tickets'] }}</div><div class="text-xs text-slate-500">{{ $totals['orders'] }} Bestellungen</div></div>
    <div class="card"><div class="text-sm text-slate-500">Eingelassen</div><div class="mt-1 text-2xl font-extrabold tracking-tight">{{ $totals['checked_in'] }}</div>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-brand" style="width: {{ $rate }}%"></div></div><div class="mt-1 text-xs text-slate-500">{{ $rate }} % der Tickets</div></div>
    <div class="card"><div class="text-sm text-slate-500">Offen / Erstattet</div><div class="mt-1 text-2xl font-extrabold tracking-tight">{{ $totals['pending'] }} <span class="text-slate-400">/</span> {{ $totals['refunded'] }}</div><div class="text-xs text-slate-500">Bestellungen</div></div>
</div>

<div class="card mb-6">
    <div class="mb-1 flex items-baseline justify-between"><h2 class="font-semibold">Umsatz pro Tag</h2><span class="text-xs text-slate-500">letzte {{ $days }} Tage</span></div>
    <div class="flex h-40 items-end gap-px" role="img" aria-label="Umsatz pro Tag">
        @foreach ($series as $d)
            <div class="group relative flex h-full flex-1 items-end" title="{{ \Illuminate\Support\Carbon::parse($d['date'])->format('d.m.Y') }}: {{ $money($d['revenue']) }}, {{ $d['tickets'] }} Tickets">
                <div class="w-full rounded-t bg-brand/80 transition-all" style="height: {{ $d['revenue'] > 0 ? max(3, round($d['revenue'] / $maxRev * 100)) : 0 }}%"></div>
            </div>
        @endforeach
    </div>
    <div class="mt-1 flex justify-between text-[11px] text-slate-400"><span>{{ \Illuminate\Support\Carbon::parse($series[0]['date'])->format('d.m.') }}</span><span>{{ \Illuminate\Support\Carbon::parse(end($series)['date'])->format('d.m.') }}</span></div>
</div>

<div class="mb-6 grid gap-6 lg:grid-cols-2">
    <div class="card">
        <h2 class="mb-3 font-semibold">Nach Ticketart</h2>
        @forelse ($byType as $t)
            <div class="mb-3 last:mb-0">
                <div class="flex justify-between text-sm"><span class="font-medium">{{ $t['name'] }}</span><span class="text-slate-500">{{ $t['count'] }} · {{ $money($t['revenue']) }}</span></div>
                <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-brand" style="width: {{ round($t['count'] / $typeMax * 100) }}%"></div></div>
            </div>
        @empty
            <p class="text-sm text-slate-500">Noch keine Verkäufe.</p>
        @endforelse
    </div>
    <div class="card">
        <h2 class="mb-3 font-semibold">Zahlungsarten und Kanäle</h2>
        <div class="mb-4 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-xl border border-slate-200 p-3"><div class="text-slate-500">Online</div><div class="font-bold">{{ $money($channels['online']['revenue'] ?? 0) }}</div><div class="text-xs text-slate-500">{{ $channels['online']['count'] ?? 0 }} Bestellungen</div></div>
            <div class="rounded-xl border border-slate-200 p-3"><div class="text-slate-500">Abendkasse</div><div class="font-bold">{{ $money($channels['door']['revenue'] ?? 0) }}</div><div class="text-xs text-slate-500">{{ $channels['door']['count'] ?? 0 }} Bestellungen</div></div>
        </div>
        @forelse ($byMethod as $m)
            <div class="flex justify-between border-t border-slate-100 py-2 text-sm first:border-0"><span>{{ $m['label'] }}</span><span class="text-slate-500">{{ $m['count'] }} · {{ $money($m['revenue']) }}</span></div>
        @empty
            <p class="text-sm text-slate-500">Noch keine Zahlungen.</p>
        @endforelse
    </div>
</div>

@if ($perEvent->isNotEmpty())
    <div class="card overflow-x-auto p-0">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold">Events im Vergleich</div>
        <table class="w-full text-left text-sm">
            <thead class="text-slate-500"><tr><th class="px-5 py-2">Event</th><th class="px-5 py-2">Verkauft</th><th class="px-5 py-2">Auslastung</th><th class="px-5 py-2">Eingelassen</th><th class="px-5 py-2 text-right">Umsatz</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($perEvent as $r)
                <tr>
                    <td class="px-5 py-3"><a class="font-medium hover:text-brand" href="{{ route('organizer.stats', [$organizer, 'event' => $r['event']->slug]) }}">{{ $r['event']->title }}</a><div class="text-xs text-slate-500">{{ $r['event']->starts_at->format('d.m.Y') }}</div></td>
                    <td class="px-5 py-3">{{ $r['sold'] }}</td>
                    <td class="px-5 py-3">@if ($r['capacity'])<div class="flex items-center gap-2"><div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-brand" style="width: {{ min(100, round($r['sold'] / $r['capacity'] * 100)) }}%"></div></div><span class="text-xs text-slate-500">{{ round($r['sold'] / $r['capacity'] * 100) }} %</span></div>@else<span class="text-slate-400">unbegrenzt</span>@endif</td>
                    <td class="px-5 py-3">{{ $r['checked_in'] }}</td>
                    <td class="px-5 py-3 text-right font-semibold">{{ \App\Support\Money::format($r['revenue'], $r['event']->currency) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
