
@extends('layouts.app')
@section('title', $event->title)
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <a href="{{ route('organizer.events.index', $organizer) }}" class="text-sm text-slate-500 hover:text-brand">← Alle Events</a>
        <h1 class="mt-1 text-2xl font-bold">{{ $event->title }}</h1>
        <p class="text-sm text-slate-500">{{ $event->starts_at->translatedFormat('l, d.m.Y H:i') }} · {{ $event->type->label() }}@if ($event->venue_name) · {{ $event->venue_name }}@endif</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $event->status->badge() }}">{{ $event->status->label() }}</span>
        <a href="{{ route('organizer.seating.show', [$organizer, $event]) }}" class="btn btn-ghost">Sitzplan</a>
        <a href="{{ route('organizer.events.edit', [$organizer, $event]) }}" class="btn btn-ghost">Bearbeiten</a>
    </div>
</div>
@error('status')<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

<div class="mb-8 flex flex-wrap gap-2">
    @foreach ([['published', 'Veröffentlichen', 'btn-primary'], ['draft', 'Zurück in den Entwurf', 'btn-ghost'], ['cancelled', 'Event absagen', 'btn-danger']] as [$value, $label, $class])
        @if ($event->status->value !== $value)
            <form method="POST" action="{{ route('organizer.events.status', [$organizer, $event]) }}" @if ($value === 'cancelled') onsubmit="return confirm('Event wirklich absagen?')" @endif>@csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ $value }}"><button class="btn {{ $class }}">{{ $label }}</button>
            </form>
        @endif
    @endforeach
</div>

<h2 class="mb-3 text-lg font-semibold">Ticketarten</h2>
<div class="space-y-3">
    @forelse ($event->ticketTypes as $t)
        <details class="card p-0" @if ($errors->any() && old('_ticket') == $t->id) open @endif>
            <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-3 px-6 py-4">
                <div>
                    <span class="font-semibold">{{ $t->name }}</span>
                    @unless ($t->is_active)<span class="ml-2 rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">inaktiv</span>@endunless
                    @if ($t->description)<div class="text-sm text-slate-500">{{ $t->description }}</div>@endif
                </div>
                <div class="text-right text-sm"><div class="font-semibold">{{ $t->price() }}</div><div class="text-slate-500">{{ $t->quota ? $t->quota.' Plätze' : 'unbegrenzt' }}</div></div>
            </summary>
            <form method="POST" action="{{ route('organizer.tickets.update', [$organizer, $event, $t]) }}" class="space-y-4 border-t border-slate-100 px-6 py-4">@csrf @method('PUT')
                <input type="hidden" name="_ticket" value="{{ $t->id }}">
                @include('organizer.events._ticket_fields', ['t' => $t, 'currency' => $event->currency])
                <div class="flex items-center gap-3">
                    <button class="btn btn-primary">Speichern</button>
                    <button form="del-{{ $t->id }}" class="btn btn-danger" onclick="return confirm('Ticketart löschen?')">Löschen</button>
                </div>
            </form>
            <form id="del-{{ $t->id }}" method="POST" action="{{ route('organizer.tickets.destroy', [$organizer, $event, $t]) }}">@csrf @method('DELETE')</form>
        </details>
    @empty
        <div class="card text-sm text-slate-500">Noch keine Ticketart. Lege unten die erste an (z. B. «Normal» und «Ermässigt»).</div>
    @endforelse
</div>

<form method="POST" action="{{ route('organizer.tickets.store', [$organizer, $event]) }}" class="card mt-6 max-w-3xl space-y-4">@csrf
    <h3 class="font-semibold">Ticketart hinzufügen</h3>
    @include('organizer.events._ticket_fields', ['t' => null, 'currency' => $event->currency])
    <button class="btn btn-primary">Hinzufügen</button>
</form>

<form method="POST" action="{{ route('organizer.events.destroy', [$organizer, $event]) }}" class="mt-10" onsubmit="return confirm('Event samt Ticketarten endgültig löschen?')">@csrf @method('DELETE')
    <button class="btn btn-danger">Event löschen</button>
</form>
@endsection
