@extends('layouts.app')
@section('title', 'Sitzplan')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<a href="{{ route('organizer.events.show', [$organizer, $event]) }}" class="text-sm text-slate-500 hover:text-brand">← {{ $event->title }}</a>
<h1 class="mb-6 mt-1 text-2xl font-bold">Sitzplan</h1>
@error('seating_mode')<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror
@error('section')<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

<form method="POST" action="{{ route('organizer.seating.mode', [$organizer, $event]) }}" class="card mb-6 flex flex-wrap items-end gap-4">@csrf @method('PUT')
    <div class="min-w-64 flex-1">
        <label class="label" for="seating_mode">Platzwahl</label>
        <select id="seating_mode" name="seating_mode" class="input">
            @foreach (\App\Enums\SeatingMode::cases() as $m)<option value="{{ $m->value }}" @selected($event->seating_mode === $m)>{{ $m->label() }}</option>@endforeach
        </select>
    </div>
    <button class="btn btn-ghost">Modus übernehmen</button>
</form>

@if ($event->seating_mode === \App\Enums\SeatingMode::None)
    <div class="card text-sm text-slate-600">Ohne Platzwahl verkaufst du nur Kontingente pro Ticketart. Wähle «Raster» für feste Reihen und Plätze oder «Saalplan» für einen frei gezeichneten Plan.</div>
@elseif ($event->ticketTypes->isEmpty())
    <div class="card text-sm text-slate-600">Lege zuerst Ticketarten an. Jeder Sitzbereich bekommt den Preis einer Ticketart. <a class="text-brand" href="{{ route('organizer.events.show', [$organizer, $event]) }}">Zu den Ticketarten</a></div>

@elseif ($event->seating_mode === \App\Enums\SeatingMode::Grid)
    <div class="space-y-3">
        @forelse ($event->sections as $s)
            <div class="card">
                <form method="POST" action="{{ route('organizer.seating.sections.update', [$organizer, $event, $s]) }}" class="flex flex-wrap items-end gap-3">@csrf @method('PUT')
                    <x-field name="name" label="Bereich" :value="$s->name" class="min-w-48 flex-1" />
                    <div>
                        <label class="label">Ticketart (Preis)</label>
                        <select name="ticket_type_id" class="input">@foreach ($event->ticketTypes as $t)<option value="{{ $t->id }}" @selected($s->ticket_type_id === $t->id)>{{ $t->name }} ({{ $t->price() }})</option>@endforeach</select>
                    </div>
                    <span class="pb-2 text-sm text-slate-500">{{ $seatCounts[$s->id] ?? 0 }} Plätze</span>
                    <button class="btn btn-ghost">Speichern</button>
                    <button form="del-s-{{ $s->id }}" class="btn btn-danger" onclick="return confirm('Bereich mit allen Plätzen löschen?')">Löschen</button>
                </form>
                <form id="del-s-{{ $s->id }}" method="POST" action="{{ route('organizer.seating.sections.destroy', [$organizer, $event, $s]) }}">@csrf @method('DELETE')</form>
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm text-slate-500">Einzelne Plätze sperren</summary>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($s->seats()->orderBy('y')->orderBy('x')->get() as $seat)
                            <form method="POST" action="{{ route('organizer.seating.seats.toggle', [$organizer, $event, $seat]) }}">@csrf @method('PATCH')
                                <button class="rounded px-2 py-1 text-xs {{ $seat->is_blocked ? 'bg-red-100 text-red-700 line-through' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}" title="{{ $seat->is_blocked ? 'Freigeben' : 'Sperren' }}">{{ $seat->label }}</button>
                            </form>
                        @endforeach
                    </div>
                </details>
            </div>
        @empty
            <div class="card text-sm text-slate-500">Noch keine Bereiche. Lege unten den ersten an.</div>
        @endforelse
    </div>
    <form method="POST" action="{{ route('organizer.seating.sections.store', [$organizer, $event]) }}" class="card mt-6 max-w-2xl space-y-4">@csrf
        <h2 class="font-semibold">Bereich hinzufügen</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Name" placeholder="Parkett" required />
            <div>
                <label class="label" for="ticket_type_id">Ticketart (bestimmt den Preis)</label>
                <select id="ticket_type_id" name="ticket_type_id" class="input">@foreach ($event->ticketTypes as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->price() }})</option>@endforeach</select>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field name="rows" label="Anzahl Reihen" type="number" min="1" max="60" value="10" required />
            <x-field name="seats_per_row" label="Plätze pro Reihe" type="number" min="1" max="80" value="20" required />
            <x-field name="first_row" label="Erste Reihe" value="A" required hint="A oder 1" />
        </div>
        <button class="btn btn-primary">Erzeugen</button>
    </form>

@else
    <div x-data="seatEditor(@js($editor))" @keydown.window="keydown($event)" class="grid gap-4 lg:grid-cols-[1fr_18rem]">
        <div class="card p-3">
            <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                <template x-for="t in [['select','Auswählen'],['seat','Einzelplatz'],['row','Reihe ziehen']]" :key="t[0]">
                    <button type="button" @click="tool = t[0]; rowStart = null" class="btn" :class="tool === t[0] ? 'btn-primary' : 'btn-ghost'" x-text="t[1]"></button>
                </template>
                <label class="ml-2 flex items-center gap-1 text-slate-600">Reihe <input x-model="rowName" class="input w-16 py-1"></label>
                <span class="ml-auto flex items-center gap-1">
                    <button type="button" class="btn btn-ghost px-2" @click="zoom = Math.max(.5, zoom - .25)">−</button>
                    <button type="button" class="btn btn-ghost px-2" @click="zoom = Math.min(3, zoom + .25)">+</button>
                </span>
            </div>
            <p class="mb-2 text-xs text-slate-500" x-show="tool === 'seat'">Klicke auf die Fläche, um Plätze zu setzen.</p>
            <p class="mb-2 text-xs text-slate-500" x-show="tool === 'row'" x-cloak x-text="rowStart ? 'Jetzt den Endpunkt der Reihe anklicken.' : 'Klicke den Anfang der Reihe an.'"></p>
            <p class="mb-2 text-xs text-slate-500" x-show="tool === 'select'" x-cloak>Plätze anklicken oder mit Rahmen aufziehen (Shift = mehrere), ziehen zum Verschieben, Entf zum Löschen.</p>
            <div class="overflow-auto rounded-lg border border-slate-200 bg-slate-50" style="max-height:70vh">
                <svg x-ref="canvas" :viewBox="viewBox" :style="`width:${zoom * 100}%;min-width:600px;touch-action:none;user-select:none`"
                     @pointerdown="canvasDown($event)" @pointermove="move($event)" @pointerup="up()" @pointerleave="up()"
                     :class="tool === 'select' ? 'cursor-default' : 'cursor-crosshair'">
                    <defs><pattern id="grid" width="30" height="30" patternUnits="userSpaceOnUse"><path d="M30 0H0V30" fill="none" stroke="#e2e8f0" stroke-width="1"/></pattern></defs>
                    <rect width="100%" height="100%" fill="url(#grid)"/>
                    <template x-for="seat in seats" :key="seat.key">
                        <g @pointerdown="seatDown($event, seat)" class="cursor-pointer">
                            <circle :cx="seat.x" :cy="seat.y" r="11" :fill="seat.is_blocked ? '#cbd5e1' : color(seat)" :stroke="isSelected(seat) ? '#0f172a' : (seat.sold ? '#f59e0b' : 'white')" :stroke-width="isSelected(seat) ? 3 : (seat.sold ? 3 : 1.5)"/>
                            <text :x="seat.x" :y="seat.y + 3" text-anchor="middle" font-size="8" fill="white" style="pointer-events:none" x-text="seat.number || ''"></text>
                            <title x-text="seat.label + (seat.is_blocked ? ' (gesperrt)' : '') + (seat.sold ? ' (hat Bestellungen)' : '')"></title>
                        </g>
                    </template>
                    <template x-if="rowStart"><circle :cx="rowStart.x" :cy="rowStart.y" r="6" fill="#0f172a"/></template>
                    <template x-if="bandRect"><rect :x="bandRect.x" :y="bandRect.y" :width="bandRect.w" :height="bandRect.h" fill="#1867f833" stroke="#1867f8"/></template>
                </svg>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card space-y-3">
                <div class="flex items-center justify-between"><h2 class="font-semibold">Bereiche</h2><button type="button" class="text-sm text-brand" @click="addSection()">+ Bereich</button></div>
                <template x-for="(s, i) in sections" :key="s.key">
                    <div class="space-y-2 rounded-lg border p-3" :class="activeSection === i ? 'border-brand' : 'border-slate-200'" @click="activeSection = i">
                        <div class="flex items-center gap-2"><span class="h-3 w-3 shrink-0 rounded-full" :style="`background:${sectionColor(i)}`"></span>
                            <input x-model="s.name" @input="dirty = true" class="input py-1"><button type="button" class="text-xs text-red-600" @click.stop="removeSection(i)">✕</button></div>
                        <select x-model.number="s.ticket_type_id" @change="dirty = true" class="input py-1 text-xs"><template x-for="t in ticketTypes" :key="t.id"><option :value="t.id" :selected="t.id === s.ticket_type_id" x-text="t.name"></option></template></select>
                        <div class="text-xs text-slate-500"><span x-text="countIn(i)"></span> Plätze<span x-show="activeSection === i"> · neue Plätze landen hier</span></div>
                    </div>
                </template>
            </div>

            <div class="card space-y-3" x-show="selected.length" x-cloak>
                <h2 class="font-semibold"><span x-text="selected.length"></span> ausgewählt</h2>
                <div x-show="single"><label class="label">Bezeichnung</label><input :value="single?.label" @input="renameSingle($event.target.value)" class="input py-1"></div>
                <div><label class="label">Bereich</label>
                    <select @change="assignSection($event.target.value)" class="input py-1"><option value="">Wechseln …</option><template x-for="(s, i) in sections" :key="s.key"><option :value="i" x-text="s.name"></option></template></select></div>
                <div class="flex gap-2"><button type="button" class="btn btn-ghost" @click="toggleBlocked()">Sperren / Freigeben</button><button type="button" class="btn btn-danger" @click="removeSelected()">Löschen</button></div>
            </div>

            <div class="card space-y-2">
                <button type="button" class="btn btn-primary w-full" @click="save()" :disabled="saving" x-text="saving ? 'Speichert …' : 'Plan speichern'"></button>
                <p class="text-xs text-amber-700" x-show="dirty" x-cloak>Ungespeicherte Änderungen.</p>
                <p class="text-sm text-emerald-700" x-show="message" x-text="message" x-cloak></p>
                <p class="text-sm text-red-600" x-show="error" x-text="error" x-cloak></p>
                <p class="text-xs text-slate-500">Gesperrte Plätze (grau) werden nicht verkauft. Orange Rand: Platz hat Bestellungen.</p>
            </div>
        </div>
    </div>
@endif
@endsection
