@extends('layouts.app')
@section('title', 'Kassenabschluss')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
@php($money = fn ($c) => \App\Support\Money::format($c, $event->currency))
<a href="{{ route('organizer.door.sessions', [$organizer, $event]) }}" class="text-sm text-slate-500 hover:text-brand print:hidden">← Alle Schichten</a>
<div class="mt-2 mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold">Kassenabschluss</h1>
        <p class="text-sm text-slate-500">{{ $event->title }} · {{ $session->user->name }} · {{ $session->opened_at->format('d.m.Y H:i') }} bis {{ $session->closed_at?->format('H:i') ?? 'offen' }}</p>
    </div>
    <div class="flex gap-2 print:hidden">
        <button type="button" onclick="window.print()" class="btn btn-ghost">Drucken</button>
        <a href="{{ route('organizer.door.export', [$organizer, $event, $session]) }}" class="btn btn-ghost">CSV-Export</a>
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div class="card"><div class="text-sm text-slate-500">Umsatz total</div><div class="text-2xl font-bold">{{ $money($stats['paid']) }}</div><div class="text-sm text-slate-500">{{ $stats['tickets'] }} Tickets</div></div>
    <div class="card"><div class="text-sm text-slate-500">Bargeld laut System</div><div class="text-2xl font-bold">{{ $money($stats['expected_cash']) }}</div><div class="text-sm text-slate-500">inkl. {{ $money($session->opening_float_cents) }} Anfangsbestand</div></div>
    @if ($session->counted_cash_cents !== null)
        @php($diff = $session->counted_cash_cents - $stats['expected_cash'])
        <div class="card"><div class="text-sm text-slate-500">Gezählt</div><div class="text-2xl font-bold">{{ $money($session->counted_cash_cents) }}</div>
            <div class="text-sm font-medium {{ $diff === 0 ? 'text-emerald-700' : 'text-red-600' }}">Differenz {{ $diff > 0 ? '+' : '' }}{{ \App\Support\Money::input($diff) }}</div></div>
    @endif
</div>

<h2 class="mt-8 mb-3 font-semibold">Nach Zahlungsart</h2>
<div class="card divide-y divide-slate-100 p-0 text-sm">
    @forelse ($stats['by_method'] as $method => $row)
        <div class="flex justify-between px-6 py-3"><span>{{ \App\Models\Order::methodLabel($method) }} ({{ $row['count'] }} Verkäufe)</span><span class="font-medium">{{ $money($row['total']) }}</span></div>
    @empty
        <div class="px-6 py-3 text-slate-500">Keine Verkäufe.</div>
    @endforelse
</div>
@if ($session->note)<p class="mt-4 text-sm text-slate-600">Bemerkung: {{ $session->note }}</p>@endif

<h2 class="mt-8 mb-3 font-semibold">Alle Verkäufe</h2>
<div class="card divide-y divide-slate-100 p-0 text-sm">
    @foreach ($orders as $o)
        <div class="flex justify-between px-6 py-2 {{ $o->status !== 'paid' ? 'text-slate-400 line-through' : '' }}"><span>{{ $o->created_at->format('H:i') }} · {{ $o->number }} · {{ \App\Models\Order::methodLabel($o->payment_method) }}</span><span>{{ $money($o->total_cents) }}</span></div>
    @endforeach
</div>
@endsection
