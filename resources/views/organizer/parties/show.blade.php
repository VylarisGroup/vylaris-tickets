@extends('layouts.app')
@section('title', $party->title)
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        <a href="{{ route('organizer.parties.index', $organizer) }}" class="text-xs text-slate-500 hover:text-brand">← Einladungen</a>
        <h1 class="text-2xl font-extrabold tracking-tight">{{ $party->title }}</h1>
        <p class="text-sm text-slate-500">{{ $party->starts_at->translatedFormat('l, d.m.Y, H:i') }} Uhr @if ($party->venue_name) · {{ $party->venue_name }}@endif</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('organizer.parties.preview', [$organizer, $party]) }}" target="_blank" class="btn btn-ghost">Vorschau</a>
        <a href="{{ route('organizer.parties.mail-preview', [$organizer, $party]) }}" target="_blank" class="btn btn-ghost">Mail ansehen</a>
        <a href="{{ route('organizer.parties.edit', [$organizer, $party]) }}" class="btn btn-ghost"><x-icon name="edit" :size="16" />Bearbeiten</a>
    </div>
</div>

<div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <div class="card !p-4"><div class="text-2xl font-extrabold">{{ $counts['yes_people'] }}</div><div class="text-xs text-slate-500">Zugesagt ({{ $counts['yes'] }} Gäste + Begleitung)</div></div>
    <div class="card !p-4"><div class="text-2xl font-extrabold">{{ $counts['maybe'] }}</div><div class="text-xs text-slate-500">Vielleicht</div></div>
    <div class="card !p-4"><div class="text-2xl font-extrabold">{{ $counts['no'] }}</div><div class="text-xs text-slate-500">Abgesagt</div></div>
    <div class="card !p-4"><div class="text-2xl font-extrabold">{{ $counts['open'] }}</div><div class="text-xs text-slate-500">Noch offen</div></div>
</div>

<div class="card mb-6 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold">Gäste ({{ $counts['total'] }})</h2>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('organizer.parties.send', [$organizer, $party]) }}">@csrf<button class="btn btn-primary !py-1.5 text-sm"><x-icon name="mail" :size="14" />Einladungen senden</button></form>
            <form method="POST" action="{{ route('organizer.parties.remind', [$organizer, $party]) }}">@csrf<button class="btn btn-ghost !py-1.5 text-sm">Erinnern</button></form>
            <a href="{{ route('organizer.parties.guests.export', [$organizer, $party]) }}" class="btn btn-ghost !py-1.5 text-sm"><x-icon name="download" :size="14" />CSV</a>
        </div>
    </div>
    @if ($party->guests->isNotEmpty())
        <div class="divide-y divide-slate-100 rounded-xl border border-slate-200">
            @foreach ($party->guests as $g)
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $g->name }}@if ($g->response === 'yes' && $g->companions) <span class="font-normal text-slate-500">+ {{ $g->companions }}</span>@endif</div>
                        <div class="truncate text-xs text-slate-500">{{ $g->email ?: 'Ohne E-Mail (Link teilen)' }}@if ($g->invited_at) · eingeladen {{ $g->invited_at->format('d.m.') }}@endif</div>
                        @if ($g->companion_names)<div class="mt-1 text-xs text-slate-600">Begleitung: {{ implode(', ', $g->companion_names) }}</div>@endif
                        @foreach ($party->questions ?? [] as $q)
                            @if (! empty(($g->answers ?? [])[$q['id']]))<div class="mt-0.5 text-xs text-slate-600">{{ $q['label'] }}: <strong>{{ $g->answers[$q['id']] }}</strong></div>@endif
                        @endforeach
                        @if ($g->message)<div class="mt-1 text-xs italic text-slate-600">«{{ $g->message }}»</div>@endif
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ ['yes' => 'bg-emerald-100 text-emerald-800', 'maybe' => 'bg-amber-100 text-amber-800', 'no' => 'bg-red-100 text-red-800'][$g->response] ?? 'bg-slate-100 text-slate-600' }}">{{ $g->responseLabel() }}</span>
                        <button type="button" class="btn btn-ghost !px-2 !py-1 text-xs" x-data="{ ok: false }" @click="navigator.clipboard.writeText(@js($g->url())).then(() => { ok = true; setTimeout(() => ok = false, 1500) })"><span x-text="ok ? 'Kopiert' : 'Link'"></span></button>
                        @if ($g->email)
                            <form method="POST" action="{{ route('organizer.parties.send', [$organizer, $party]) }}">@csrf<input type="hidden" name="guest" value="{{ $g->id }}"><button class="btn btn-ghost !px-2 !py-1 text-xs" title="Einladung erneut senden">{{ $g->invited_at ? 'Erneut' : 'Senden' }}</button></form>
                        @endif
                        <form method="POST" action="{{ route('organizer.parties.guests.destroy', [$organizer, $party, $g]) }}" onsubmit="return confirm('Gast entfernen?')">@csrf @method('DELETE')<button class="btn btn-ghost !px-2 !py-1 text-xs" aria-label="Entfernen"><x-icon name="trash" :size="14" /></button></form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    <form method="POST" action="{{ route('organizer.parties.guests.store', [$organizer, $party]) }}" class="space-y-2">@csrf
        <label class="label" for="guests">Gäste hinzufügen</label>
        <textarea id="guests" name="guests" rows="4" class="input" placeholder="Eine Person pro Zeile:&#10;Anna Muster; anna@example.ch&#10;Ben Beispiel, ben@example.ch&#10;Cara ohne Mail"></textarea>
        <p class="text-xs text-slate-500">Ohne E-Mail-Adresse teilst du den persönlichen Link selbst (z. B. per Messenger). Du kannst auch eine Liste aus Excel einfügen.</p>
        @error('guests')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-ghost">Hinzufügen</button>
    </form>
</div>

<div class="card mb-6 space-y-3" x-data="{ open: {{ $errors->hasAny(['subject', 'body', 'audience']) ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between"><h2 class="font-semibold">Nachricht an Gäste</h2><button type="button" class="btn btn-ghost !py-1.5 text-sm" @click="open = !open" x-text="open ? 'Schliessen' : 'Schreiben'"></button></div>
    <form method="POST" action="{{ route('organizer.parties.message', [$organizer, $party]) }}" x-show="open" x-cloak class="space-y-3">@csrf
        <div><label class="label" for="audience">Empfänger</label>
            <select id="audience" name="audience" class="input sm:w-72">
                @foreach (['all' => 'Alle Gäste', 'yes' => 'Nur Zugesagte', 'maybe' => 'Nur «Vielleicht»', 'open' => 'Nur ohne Antwort', 'no' => 'Nur Abgesagte'] as $k => $l)<option value="{{ $k }}" @selected(old('audience', 'all') === $k)>{{ $l }}</option>@endforeach
            </select></div>
        <x-field name="subject" label="Betreff" maxlength="150" required />
        <div><label class="label" for="body">Nachricht</label><textarea id="body" name="body" rows="5" maxlength="5000" required class="input">{{ old('body') }}</textarea>
            @error('body')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <button class="btn btn-primary" onclick="return confirm('Nachricht jetzt an die gewählten Gäste senden?')">Senden</button>
    </form>
</div>

@if ($party->allow_items)
<div class="card mb-6 space-y-3">
    <h2 class="font-semibold">Mitbringliste</h2>
    @forelse ($party->items as $i)
        <div class="flex items-center justify-between gap-2 text-sm"><span>{{ $i->label }} <span class="text-slate-500">· {{ $i->guest?->name ?? 'noch frei' }}</span></span>
            <form method="POST" action="{{ route('organizer.parties.items.destroy', [$organizer, $party, $i]) }}">@csrf @method('DELETE')<button class="text-xs text-slate-400 hover:text-red-600" aria-label="Entfernen"><x-icon name="x" :size="14" /></button></form></div>
    @empty
        <p class="text-sm text-slate-500">Noch leer. Trag ein, was gebraucht wird (z. B. «Salat», «Getränke»).</p>
    @endforelse
    <form method="POST" action="{{ route('organizer.parties.items.store', [$organizer, $party]) }}" class="flex gap-2">@csrf
        <input name="label" required maxlength="120" class="input" placeholder="Was wird gebraucht?"><button class="btn btn-ghost">Hinzufügen</button>
    </form>
</div>
@endif

@if ($party->allow_comments)
<div class="card mb-6 space-y-3">
    <h2 class="font-semibold">Kommentare</h2>
    <form method="POST" action="{{ route('organizer.parties.comments.store', [$organizer, $party]) }}" class="flex gap-2">@csrf
        <input name="body" required maxlength="1000" class="input" placeholder="Nachricht an alle Gäste"><button class="btn btn-ghost">Senden</button>
    </form>
    @foreach ($party->comments as $c)
        <div class="flex items-start justify-between gap-2 text-sm"><div><span class="font-medium">{{ $c->author }}@if ($c->by_host) <span class="text-xs text-brand">Gastgeber</span>@endif</span> <span class="text-xs text-slate-400">{{ $c->created_at->format('d.m. H:i') }}</span><div class="text-slate-700">{{ $c->body }}</div></div>
            <form method="POST" action="{{ route('organizer.parties.comments.destroy', [$organizer, $party, $c]) }}">@csrf @method('DELETE')<button class="text-xs text-slate-400 hover:text-red-600" aria-label="Löschen"><x-icon name="trash" :size="14" /></button></form></div>
    @endforeach
</div>
@endif

<form method="POST" action="{{ route('organizer.parties.destroy', [$organizer, $party]) }}" class="mt-10" onsubmit="return confirm('Einladung samt Gästeliste endgültig löschen?')">@csrf @method('DELETE')
    <button class="btn btn-danger">Einladung löschen</button>
</form>
@endsection
