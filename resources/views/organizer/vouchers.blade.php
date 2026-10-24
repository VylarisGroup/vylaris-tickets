@extends('layouts.app')
@section('title', 'Gutscheine')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">Gutscheine und Rabattcodes</h1>
<div class="card mb-8 overflow-x-auto p-0">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 text-slate-500"><tr><th class="px-6 py-3">Code</th><th class="px-6 py-3">Rabatt</th><th class="px-6 py-3">Gilt für</th><th class="px-6 py-3">Eingelöst</th><th class="px-6 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($vouchers as $v)
            <tr>
                <td class="px-6 py-3 font-mono font-medium">{{ $v->code }}</td>
                <td class="px-6 py-3">{{ $v->type === 'percent' ? $v->value.' %' : \App\Support\Money::format($v->value, $organizer->default_currency) }}</td>
                <td class="px-6 py-3">{{ $v->event?->title ?? 'Alle Events' }}</td>
                <td class="px-6 py-3">{{ $v->used_count }}@if ($v->max_uses) / {{ $v->max_uses }}@endif</td>
                <td class="px-6 py-3 text-right">
                    <form method="POST" action="{{ route('organizer.vouchers.destroy', [$organizer, $v]) }}" onsubmit="return confirm('Gutschein löschen?')">@csrf @method('DELETE')<button class="text-red-600">Löschen</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-6 py-4 text-slate-500">Noch keine Gutscheine.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<form method="POST" action="{{ route('organizer.vouchers.store', $organizer) }}" class="card max-w-2xl space-y-4">@csrf
    <h2 class="font-semibold">Gutschein anlegen</h2>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="code" label="Code" required placeholder="FRUEHLING25" class="sm:col-span-1" />
        <div>
            <label class="label" for="type">Art</label>
            <select id="type" name="type" class="input">
                <option value="percent" @selected(old('type') === 'percent')>Prozent</option>
                <option value="fixed" @selected(old('type') === 'fixed')>Fester Betrag pro Ticket ({{ $organizer->default_currency }})</option>
            </select>
        </div>
        <x-field name="value" label="Wert" required placeholder="10" />
    </div>
    <div>
        <label class="label" for="event_id">Gilt für</label>
        <select id="event_id" name="event_id" class="input">
            <option value="">Alle Events</option>
            @foreach ($events as $e)<option value="{{ $e->id }}" @selected((string) old('event_id') === (string) $e->id)>{{ $e->title }}</option>@endforeach
        </select>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="max_uses" label="Max. Einlösungen" type="number" min="1" hint="Leer = unbegrenzt." />
        <x-field name="valid_from" label="Gültig ab" type="datetime-local" />
        <x-field name="valid_until" label="Gültig bis" type="datetime-local" />
    </div>
    <button class="btn btn-primary">Anlegen</button>
</form>
@endsection
