@php($dt = fn ($d) => $d?->format('Y-m-d\TH:i'))
@extends('layouts.app')
@section('title', 'Gutschein bearbeiten')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<a href="{{ route('organizer.vouchers.index', $organizer) }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand"><x-icon name="arrow-left" :size="14" />Gutscheine</a>
<h1 class="mb-6 mt-2 text-2xl font-extrabold tracking-tight">Gutschein <span class="font-mono">{{ $voucher->code }}</span></h1>
<form method="POST" action="{{ route('organizer.vouchers.update', [$organizer, $voucher]) }}" class="card max-w-2xl space-y-4">@csrf @method('PUT')
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label" for="type">Art</label>
            <select id="type" name="type" class="input">
                <option value="percent" @selected(old('type', $voucher->type) === 'percent')>Prozent</option>
                <option value="fixed" @selected(old('type', $voucher->type) === 'fixed')>Fester Betrag pro Ticket ({{ $organizer->default_currency }})</option>
            </select>
        </div>
        <x-field name="value" label="Wert" required :value="$voucher->type === 'percent' ? $voucher->value : \App\Support\Money::input($voucher->value)" />
    </div>
    <div>
        <label class="label" for="event_id">Gilt für</label>
        <select id="event_id" name="event_id" class="input">
            <option value="">Alle Events</option>
            @foreach ($events as $e)<option value="{{ $e->id }}" @selected((string) old('event_id', $voucher->event_id) === (string) $e->id)>{{ $e->title }}</option>@endforeach
        </select>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="max_uses" label="Max. Einlösungen" type="number" min="1" :value="$voucher->max_uses" hint="Bisher eingelöst: {{ $voucher->used_count }}. Leer = unbegrenzt." />
        <x-field name="valid_from" label="Gültig ab" type="datetime-local" :value="$dt($voucher->valid_from)" />
        <x-field name="valid_until" label="Gültig bis" type="datetime-local" :value="$dt($voucher->valid_until)" />
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $voucher->is_active)) class="rounded border-slate-300"> Aktiv</label>
    <div class="flex gap-3"><button class="btn btn-primary">Speichern</button><a href="{{ route('organizer.vouchers.index', $organizer) }}" class="btn btn-ghost">Abbrechen</a></div>
</form>
@endsection
