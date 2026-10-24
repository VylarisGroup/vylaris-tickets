@extends('layouts.app')
@section('title', 'Abendkasse')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<style>html, body { overscroll-behavior-y: contain; }</style>
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <a href="{{ route('organizer.door.index', $organizer) }}" class="text-sm text-slate-500 hover:text-brand">← Abendkasse</a>
        <h1 class="mt-1 text-2xl font-bold">{{ $event->title }}</h1>
        <p class="text-sm text-slate-500">Schicht seit {{ $session->opened_at->format('H:i') }} Uhr · Anfangsbestand {{ \App\Support\Money::format($session->opening_float_cents, $event->currency) }}</p>
    </div>
    <div class="text-right text-sm">
        <div class="text-slate-500">Umsatz dieser Schicht</div>
        <div class="text-2xl font-bold">{{ \App\Support\Money::format($stats['paid'], $event->currency) }}</div>
        <div class="text-slate-500">{{ $stats['tickets'] }} Tickets</div>
    </div>
</div>

@if ($errors->has('checkout'))
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('checkout') }}</div>
@endif

<div class="grid gap-6 lg:grid-cols-3">
<form method="POST" action="{{ route('organizer.door.sell', [$organizer, $event]) }}" x-data="shop(@js($config))" class="space-y-6 lg:col-span-2">@csrf
    <div class="card space-y-3">
        <h2 class="text-lg font-semibold">Tickets</h2>
        @foreach ($types as $t)
            @continue($t['seated'])
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                <div>
                    <div class="font-medium">{{ $t['name'] }}</div>
                    <div class="text-sm font-semibold">{{ $t['price'] }}</div>
                </div>
                @if ($t['sold_out'])
                    <span class="text-sm font-medium text-red-600">Ausverkauft</span>
                @else
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn btn-ghost h-11 w-11 text-xl" @click="items[{{ $t['id'] }}] = Math.max(0, Number(items[{{ $t['id'] }}]) - 1)" aria-label="Weniger">−</button>
                        <input type="number" min="0" max="{{ $t['max'] }}" name="items[{{ $t['id'] }}]" x-model.number="items[{{ $t['id'] }}]" class="input h-11 w-20 text-center text-lg">
                        <button type="button" class="btn btn-ghost h-11 w-11 text-xl" @click="items[{{ $t['id'] }}] = Math.min({{ $t['max'] }}, Number(items[{{ $t['id'] }}]) + 1)" aria-label="Mehr">+</button>
                    </div>
                @endif
            </div>
        @endforeach
        @if ($config['seating'])
            <div><h3 class="mb-2 font-medium">Platzwahl</h3><div class="space-y-3">@include('shop._seat_picker')</div></div>
        @endif
    </div>

    <div class="card space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <span class="label">Zahlungsart</span>
                <div class="flex gap-2">
                    <label class="flex-1 rounded-lg border border-slate-200 px-4 py-3 text-center text-sm has-[:checked]:border-brand has-[:checked]:bg-brand/5"><input type="radio" name="method" value="cash" class="mr-2 text-brand" checked>Bar</label>
                    <label class="flex-1 rounded-lg border border-slate-200 px-4 py-3 text-center text-sm has-[:checked]:border-brand has-[:checked]:bg-brand/5"><input type="radio" name="method" value="external" class="mr-2 text-brand">Karte/TWINT</label>
                </div>
                <p class="mt-1 text-xs text-slate-500">«Karte/TWINT» gilt für Zahlungen über dein eigenes Terminal und zählt nicht ins Bargeld.</p>
            </div>
            <x-field name="voucher" label="Gutscheincode (optional)" />
            <x-field name="name" label="Name (optional)" />
            <x-field name="email" label="E-Mail für Tickets (optional)" type="email" />
        </div>
        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
            <div class="text-xl font-semibold">Total <span x-text="format(total)"></span></div>
            <button class="btn btn-primary h-12 px-8 text-base" :disabled="count === 0" :class="count === 0 && 'opacity-50'">Verkaufen</button>
        </div>
    </div>
</form>

<div class="space-y-6">
    <div class="card">
        <h2 class="mb-3 font-semibold">Letzte Verkäufe</h2>
        <ul class="space-y-2 text-sm">
            @forelse ($recent as $o)
                <li class="flex items-center justify-between gap-2 {{ $o->status !== 'paid' ? 'text-slate-400 line-through' : '' }}">
                    <a class="text-brand hover:underline" href="{{ route('organizer.door.receipt', [$organizer, $event, $o]) }}">{{ $o->number }}</a>
                    <span>{{ \App\Support\Money::format($o->total_cents, $o->currency) }}</span>
                </li>
            @empty
                <li class="text-slate-500">Noch keine Verkäufe.</li>
            @endforelse
        </ul>
    </div>
    <form method="POST" action="{{ route('organizer.door.close', [$organizer, $event, $session]) }}" class="card space-y-3" onsubmit="return confirm('Kasse jetzt abschliessen?')">@csrf
        <h2 class="font-semibold">Kassenabschluss</h2>
        <div class="text-sm text-slate-600">Bargeld laut System: <strong>{{ \App\Support\Money::format($stats['expected_cash'], $event->currency) }}</strong> (inkl. Anfangsbestand)</div>
        <x-field name="counted_cash" :label="'Gezähltes Bargeld ('.$event->currency.')'" inputmode="decimal" required />
        <x-field name="note" label="Bemerkung (optional)" />
        <button class="btn btn-ghost w-full">Kasse abschliessen</button>
    </form>
</div>
</div>
@endsection
