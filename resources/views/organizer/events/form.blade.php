@php($dt = fn ($d) => $d?->format('Y-m-d\TH:i'))
@extends('layouts.app')
@section('title', $event->exists ? 'Event bearbeiten' : 'Event anlegen')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">{{ $event->exists ? 'Event bearbeiten' : 'Event anlegen' }}</h1>
<form method="POST" action="{{ $event->exists ? route('organizer.events.update', [$organizer, $event]) : route('organizer.events.store', $organizer) }}"
      class="max-w-3xl space-y-6" x-data="{ type: '{{ old('type', $event->type->value) }}' }">
    @csrf
    @if ($event->exists) @method('PUT') @endif

    <div class="card space-y-4">
        <h2 class="font-semibold">Allgemein</h2>
        <x-field name="title" label="Titel" :value="$event->title" required />
        <div>
            <label class="label" for="description">Beschreibung</label>
            <textarea id="description" name="description" rows="5" class="input">{{ old('description', $event->description) }}</textarea>
            @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="type">Art der Veranstaltung</label>
            <select id="type" name="type" x-model="type" class="input">
                @foreach (\App\Enums\EventType::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
            </select>
        </div>
    </div>

    <div class="card space-y-4">
        <h2 class="font-semibold">Zeit</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field name="starts_at" label="Beginn" type="datetime-local" :value="$dt($event->starts_at)" required />
            <x-field name="ends_at" label="Ende" type="datetime-local" :value="$dt($event->ends_at)" />
            <x-field name="doors_open_at" label="Einlass ab" type="datetime-local" :value="$dt($event->doors_open_at)" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="sales_start_at" label="Vorverkauf ab" type="datetime-local" :value="$dt($event->sales_start_at)" hint="Leer = sofort nach Veröffentlichung." />
            <x-field name="sales_end_at" label="Vorverkauf bis" type="datetime-local" :value="$dt($event->sales_end_at)" hint="Leer = bis zum Beginn." />
        </div>
    </div>

    <div class="card space-y-4" x-show="type !== 'digital'" x-cloak>
        <h2 class="font-semibold">Ort</h2>
        <x-field name="venue_name" label="Name des Orts" :value="$event->venue_name" />
        <x-field name="venue_address" label="Adresse" :value="$event->venue_address" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="door_sales_enabled" value="1" @checked(old('door_sales_enabled', $event->door_sales_enabled)) class="rounded border-slate-300"> Verkauf an der Abendkasse erlauben</label>
    </div>

    <div class="card space-y-4" x-show="type !== 'onsite'" x-cloak>
        <h2 class="font-semibold">Digitaler Zugang</h2>
        <x-field name="online_url" label="Link zum Stream / Meeting" type="url" :value="$event->online_url" hint="Wird nur an Ticketinhaber ausgeliefert, nie öffentlich angezeigt." />
        <div>
            <label class="label" for="online_instructions">Hinweise für Teilnehmende</label>
            <textarea id="online_instructions" name="online_instructions" rows="3" class="input">{{ old('online_instructions', $event->online_instructions) }}</textarea>
        </div>
    </div>

    <div class="card space-y-4">
        <h2 class="font-semibold">Preise</h2>
        <div>
            <label class="label" for="currency">Währung</label>
            <select id="currency" name="currency" class="input sm:w-40">
                @foreach (config('tickets.currencies') as $c)<option @selected(old('currency', $event->currency) === $c)>{{ $c }}</option>@endforeach
            </select>
        </div>
    </div>

    <div class="flex gap-3">
        <button class="btn btn-primary">Speichern</button>
        <a href="{{ $event->exists ? route('organizer.events.show', [$organizer, $event]) : route('organizer.events.index', $organizer) }}" class="btn btn-ghost">Abbrechen</a>
    </div>
</form>
@endsection
