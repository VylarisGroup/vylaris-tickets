@extends('layouts.public')
@section('title', $event->title)
@section('content')
@php
    $config = [
        'types' => $types->keyBy('id'),
        'currency' => $event->currency,
        'seating' => $seatData,
        'old' => ['items' => old('items', []), 'seats' => array_map('intval', old('seats', []))],
    ];
@endphp
<a href="{{ route('shop.organizer', $organizer) }}" class="text-sm text-slate-500 hover:text-brand">← {{ $organizer->name }}</a>
<div class="mt-2 mb-6">
    <div class="text-sm font-medium text-brand">{{ $event->starts_at->translatedFormat('l, d. F Y · H:i') }} Uhr{{ $event->type !== \App\Enums\EventType::Onsite ? ' · '.$event->type->label() : '' }}</div>
    <h1 class="mt-1 text-3xl font-bold">{{ $event->title }}</h1>
    @if ($event->type->hasVenue())<p class="text-slate-600">{{ $event->venue_name }}@if ($event->venue_address), {{ $event->venue_address }}@endif</p>@endif
    @if ($event->doors_open_at)<p class="text-sm text-slate-500">Einlass ab {{ $event->doors_open_at->format('H:i') }} Uhr</p>@endif
</div>

@if ($event->status === \App\Enums\EventStatus::Draft || $event->is_blocked)
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Vorschau: Dieses Event ist nicht öffentlich sichtbar.</div>
@endif
@if ($event->status === \App\Enums\EventStatus::Cancelled)
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Diese Veranstaltung wurde abgesagt.</div>
@endif

@if ($event->description)
    <div class="card mb-6 text-slate-700">{!! nl2br(e($event->description)) !!}</div>
@endif

@if ($errors->has('checkout'))
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('checkout') }}</div>
@endif

@if ($isOnSale)
<form method="POST" action="{{ route('shop.order', [$organizer, $event]) }}" x-data="shop(@js($config))" class="space-y-6">
    @csrf
    <div class="card space-y-4">
        <h2 class="text-lg font-semibold">Tickets</h2>
        @foreach ($types as $t)
            @continue($t['seated'])
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-4 last:border-0 last:pb-0">
                <div>
                    <div class="font-medium">{{ $t['name'] }}</div>
                    @if ($t['description'])<div class="text-sm text-slate-500">{{ $t['description'] }}</div>@endif
                    <div class="text-sm font-semibold">{{ $t['price'] }}</div>
                </div>
                @if ($t['sold_out'])
                    <span class="text-sm font-medium text-red-600">Ausverkauft</span>
                @elseif (! $t['on_sale'])
                    <span class="text-sm text-slate-500">Nicht im Verkauf</span>
                @else
                    <select name="items[{{ $t['id'] }}]" x-model.number="items[{{ $t['id'] }}]" class="input w-24">
                        @for ($i = 0; $i <= $t['max']; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                    </select>
                @endif
            </div>
        @endforeach

        @if ($seatData)
            <div>
                <h3 class="mb-2 font-medium">Platzwahl</h3>
                <div class="space-y-3">
                    @include('shop._seat_picker')
                </div>
            </div>
        @endif

        @if ($types->where('seated', false)->isEmpty() && ! $seatData)
            <p class="text-sm text-slate-500">Für dieses Event sind aktuell keine Tickets verfügbar.</p>
        @endif
    </div>

    <div class="card space-y-4">
        <h2 class="text-lg font-semibold">Deine Angaben</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Name" required autocomplete="name" />
            <x-field name="email" label="E-Mail" type="email" required autocomplete="email" hint="Hierhin senden wir deine Tickets." />
        </div>
        <x-field name="voucher" label="Gutscheincode (optional)" :value="old('voucher')" class="sm:w-64" />
    </div>

    <div class="card space-y-3">
        <h2 class="text-lg font-semibold">Zahlung</h2>
        <template x-if="total > 0">
            <div class="space-y-2">
                @forelse ($methods as $key => $label)
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand/5">
                        <input type="radio" name="method" value="{{ $key }}" @checked(old('method', array_key_first($methods)) === $key) class="text-brand">{{ $label }}
                    </label>
                @empty
                    <p class="text-sm text-red-600">Dieser Veranstalter hat noch keine Zahlungsart eingerichtet.</p>
                @endforelse
            </div>
        </template>
        <p x-show="total === 0" x-cloak class="text-sm text-slate-600">Kostenlose Tickets: keine Zahlung nötig.</p>
        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
            <div class="text-lg font-semibold">Total <span x-text="format(total)"></span></div>
            <button class="btn btn-primary" :disabled="count === 0" :class="count === 0 && 'opacity-50'">Weiter</button>
        </div>
        <p class="text-xs text-slate-500">Der angezeigte Betrag ist ohne Gutschein. Einen Rabatt ziehen wir beim Abschluss ab.</p>
    </div>
</form>
@else
    <div class="card text-slate-600">
        @if ($event->status === \App\Enums\EventStatus::Cancelled)
            Der Ticketverkauf ist beendet.
        @elseif ($event->sales_start_at && $event->sales_start_at->isFuture())
            Der Vorverkauf startet am {{ $event->sales_start_at->translatedFormat('d.m.Y \u\m H:i') }} Uhr.
        @else
            Der Vorverkauf ist beendet.@if ($event->door_sales_enabled && $event->type->hasVenue() && $event->status === \App\Enums\EventStatus::Published) Tickets gibt es evtl. an der Abendkasse.@endif
        @endif
    </div>
@endif

@php($soldOut = $types->where('sold_out', true)->where('seated', false))
@if ($isOnSale && $soldOut->isNotEmpty())
    <details class="card mt-6" @if (session('sold_out_type')) open @endif>
        <summary class="cursor-pointer font-semibold">Ausverkauft? Auf die Warteliste setzen</summary>
        <form method="POST" action="{{ route('shop.waitlist', [$organizer, $event]) }}" class="mt-4 grid gap-4 sm:grid-cols-2">@csrf
            <x-field name="name" label="Name" required />
            <x-field name="email" label="E-Mail" type="email" required />
            <div>
                <label class="label" for="wl_type">Ticketart</label>
                <select id="wl_type" name="ticket_type_id" class="input">
                    @foreach ($soldOut as $t)<option value="{{ $t['id'] }}" @selected(session('sold_out_type') == $t['id'])>{{ $t['name'] }}</option>@endforeach
                </select>
            </div>
            <x-field name="quantity" label="Anzahl" type="number" min="1" max="20" value="1" />
            <div class="sm:col-span-2"><button class="btn btn-ghost">Auf die Warteliste</button></div>
        </form>
    </details>
@endif

<details class="mt-10 text-sm text-slate-500">
    <summary class="cursor-pointer">Event melden</summary>
    <form method="POST" action="{{ route('shop.report', [$organizer, $event]) }}" class="card mt-3 max-w-lg space-y-3">@csrf
        <div>
            <label class="label" for="reason">Grund</label>
            <select id="reason" name="reason" class="input">@foreach ($reasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        </div>
        <div><label class="label" for="message">Details</label><textarea id="message" name="message" rows="3" class="input"></textarea></div>
        <x-field name="reporter_email" label="Deine E-Mail (optional)" type="email" />
        <button class="btn btn-ghost">Meldung senden</button>
    </form>
</details>
@endsection
