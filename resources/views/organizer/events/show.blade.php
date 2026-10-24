
@extends('layouts.app')
@section('title', $event->title)
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="flex min-w-0 items-start gap-4">
        @if ($event->coverUrl())<img src="{{ $event->coverUrl() }}" alt="" class="hidden h-20 w-32 shrink-0 rounded-xl object-cover sm:block">@endif
        <div class="min-w-0">
            <a href="{{ route('organizer.events.index', $organizer) }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand"><x-icon name="arrow-left" :size="14" />Alle Events</a>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight">{{ $event->title }}</h1>
            <p class="text-sm text-slate-500">{{ $event->starts_at->translatedFormat('l, d.m.Y H:i') }} · {{ $event->type->label() }}@if ($event->venue_name) · {{ $event->venue_name }}@endif @if ($event->categoryLabel()) · {{ $event->categoryLabel() }}@endif</p>
        </div>
    </div>
    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $event->status->badge() }}">{{ $event->status->label() }}</span>
</div>

<div class="mb-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
    <a href="{{ route('organizer.orders.index', [$organizer, $event]) }}" class="nav-item border border-slate-200"><x-icon name="list" />Bestellungen</a>
    <a href="{{ route('organizer.attendees.index', [$organizer, $event]) }}" class="nav-item border border-slate-200"><x-icon name="users" />Teilnehmende</a>
    <a href="{{ route('organizer.messages.create', [$organizer, $event]) }}" class="nav-item border border-slate-200"><x-icon name="mail" />Nachricht senden</a>
    <a href="{{ route('organizer.stats', [$organizer, 'event' => $event->slug]) }}" class="nav-item border border-slate-200"><x-icon name="chart" />Statistik</a>
    <a href="{{ route('organizer.seating.show', [$organizer, $event]) }}" class="nav-item border border-slate-200"><x-icon name="seat" />Sitzplan</a>
    <a href="{{ route('organizer.events.edit', [$organizer, $event]) }}" class="nav-item border border-slate-200"><x-icon name="edit" />Bearbeiten</a>
    <a href="{{ route('shop.event', [$organizer, $event]) }}" target="_blank" rel="noopener" class="nav-item border border-slate-200"><x-icon name="external" />Öffentliche Seite</a>
    <form method="POST" action="{{ route('organizer.events.duplicate', [$organizer, $event]) }}">@csrf<button class="nav-item w-full border border-slate-200"><x-icon name="copy" />Duplizieren</button></form>
</div>

<div class="card mb-6 flex flex-wrap items-center gap-3 !p-4">
    <x-icon name="globe" class="text-slate-400" />
    <input readonly value="{{ route('shop.event', [$organizer, $event]) }}" class="input !w-auto min-w-0 flex-1 !py-2 font-mono text-xs" aria-label="Link zur Eventseite" onfocus="this.select()">
    <button type="button" class="btn btn-ghost !py-2" x-data="{ done: false }" @click="navigator.clipboard?.writeText('{{ route('shop.event', [$organizer, $event]) }}').then(() => { done = true; setTimeout(() => done = false, 1800) })" x-text="done ? 'Kopiert ✓' : 'Link kopieren'">Link kopieren</button>
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

<h2 class="mb-3 mt-10 text-lg font-semibold">Sponsoren</h2>
@error('sponsor_name')<div class="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>@enderror
<div class="card max-w-3xl space-y-4">
    <p class="text-sm text-slate-500">Logos erscheinen auf der öffentlichen Eventseite unter «Mit freundlicher Unterstützung».</p>
    @if ($event->sponsors->isNotEmpty())
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ($event->sponsors as $sp)
                <div class="rounded-xl border border-slate-200 p-3 text-center">
                    <div class="flex h-16 items-center justify-center rounded-lg bg-white p-2"><img src="{{ $sp->logoUrl() }}" alt="{{ $sp->name }}" class="max-h-full max-w-full object-contain"></div>
                    <div class="mt-2 truncate text-sm font-medium">{{ $sp->name }}</div>
                    <form method="POST" action="{{ route('organizer.sponsors.destroy', [$organizer, $event, $sp]) }}" onsubmit="return confirm('Sponsor entfernen?')">@csrf @method('DELETE')<button class="text-xs text-red-600">Entfernen</button></form>
                </div>
            @endforeach
        </div>
    @endif
    <form method="POST" action="{{ route('organizer.sponsors.store', [$organizer, $event]) }}" enctype="multipart/form-data" class="grid gap-3 border-t border-slate-200/70 pt-4 sm:grid-cols-2">@csrf
        <x-field name="sponsor_name" label="Name" required />
        <x-field name="sponsor_url" label="Link (optional)" type="url" placeholder="https://" />
        <div class="sm:col-span-2"><label class="label" for="sponsor_logo">Logo</label><input type="file" id="sponsor_logo" name="sponsor_logo" accept="image/png,image/jpeg,image/webp" required class="input">@error('sponsor_logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="sm:col-span-2"><button class="btn btn-ghost">Sponsor hinzufügen</button></div>
    </form>
</div>

@error('status')@enderror
<form method="POST" action="{{ route('organizer.events.destroy', [$organizer, $event]) }}" class="mt-10" onsubmit="return confirm('Event samt Ticketarten endgültig löschen?')">@csrf @method('DELETE')
    <button class="btn btn-danger">Event löschen</button>
</form>
@endsection
