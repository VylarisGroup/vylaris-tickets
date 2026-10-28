@php($dt = fn ($d) => $d?->format('Y-m-d\TH:i'))
@extends('layouts.app')
@section('title', $party->exists ? 'Einladung bearbeiten' : 'Neue Einladung')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-extrabold tracking-tight">{{ $party->exists ? 'Einladung bearbeiten' : 'Neue Einladung' }}</h1>
<form method="POST" action="{{ $party->exists ? route('organizer.parties.update', [$organizer, $party]) : route('organizer.parties.store', $organizer) }}" enctype="multipart/form-data"
      class="max-w-3xl space-y-6" x-data="{ theme: '{{ old('theme', $party->theme) }}', companions: {{ old('allow_companions', $party->allow_companions) ? 'true' : 'false' }} }">
    @csrf
    @if ($party->exists) @method('PUT') @endif

    <div class="card space-y-4">
        <h2 class="font-semibold">Anlass</h2>
        <x-field name="title" label="Titel" :value="$party->title" required placeholder="z. B. Sommerfest" />
        <x-field name="host_name" label="Gastgeber" :value="$party->host_name" hint="Erscheint auf der Einladung. Leer lassen für den Namen des Veranstalters." />
        <div>
            <label class="label" for="description">Beschreibung</label>
            <textarea id="description" name="description" rows="4" class="input" placeholder="Was erwartet die Gäste? Dresscode, Hinweise …">{{ old('description', $party->description) }}</textarea>
            @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="starts_at" label="Beginn" type="datetime-local" :value="$dt($party->starts_at)" required />
            <x-field name="ends_at" label="Ende (optional)" type="datetime-local" :value="$dt($party->ends_at)" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="venue_name" label="Ort" :value="$party->venue_name" />
            <x-field name="venue_address" label="Adresse" :value="$party->venue_address" autocomplete="off" />
        </div>
    </div>

    <div class="card space-y-4">
        <h2 class="font-semibold">Ablauf und Nachricht</h2>
        <div>
            <label class="label" for="invite_message">Persönliche Nachricht in der Einladungsmail (optional)</label>
            <textarea id="invite_message" name="invite_message" rows="3" class="input" placeholder="Schön, wenn du dabei bist! Bring gute Laune mit.">{{ old('invite_message', $party->invite_message) }}</textarea>
            @error('invite_message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="schedule_text">Zeitplan (optional)</label>
            <textarea id="schedule_text" name="schedule_text" rows="4" class="input font-mono text-sm" placeholder="18:00 Apéro&#10;19:30 Essen&#10;22:00 Musik und Tanz">{{ old('schedule_text', $party->scheduleText()) }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Eine Zeile pro Punkt: zuerst die Uhrzeit, dann der Text. Erscheint auf der Einladungsseite.</p>
            @error('schedule_text')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="card space-y-4">
        <h2 class="font-semibold">Gestaltung</h2>
        <div>
            <label class="label" for="cover">Titelbild</label>
            @if ($party->cover_path)
                <img src="{{ $party->coverUrl() }}" alt="" class="mb-2 h-32 w-full rounded-xl object-cover sm:w-64">
                <label class="mb-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_cover" value="1" class="rounded border-slate-300"> Bild entfernen</label>
            @endif
            <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" class="input">
            @error('cover')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <span class="label">Farbthema</span>
            <input type="hidden" name="theme" :value="theme">
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Models\Party::THEMES as $key => [$label, $c1, $c2])
                    <button type="button" @click="theme = '{{ $key }}'" :class="theme === '{{ $key }}' ? 'ring-2 ring-brand ring-offset-2' : ''" class="flex items-center gap-2 rounded-full border border-slate-200 py-1.5 pl-1.5 pr-3 text-sm">
                        <span class="h-6 w-6 rounded-full" style="background:linear-gradient(135deg,{{ $c1 }},{{ $c2 }})"></span>{{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
        <div>
            <label class="label" for="font">Schrift</label>
            <select id="font" name="font" class="input sm:w-64">
                @foreach (\App\Models\Party::FONTS as $key => [$label, $family])
                    <option value="{{ $key }}" @selected(old('font', $party->font) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card space-y-4">
        <h2 class="font-semibold">Antworten und Extras</h2>
        <x-field name="rsvp_deadline" label="Antworten bis (optional)" type="datetime-local" :value="$dt($party->rsvp_deadline)" class="sm:w-72" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_companions" value="1" x-model="companions" class="rounded border-slate-300"> Gäste dürfen Begleitpersonen mitbringen</label>
        <div x-show="companions" x-cloak><x-field name="max_companions" label="Höchstens so viele Begleitpersonen" type="number" min="1" max="10" :value="$party->max_companions" class="sm:w-64" /></div>
        <div>
            <label class="label" for="questions_text">Fragen an die Gäste (optional)</label>
            <textarea id="questions_text" name="questions_text" rows="3" class="input font-mono text-sm" placeholder="Essen | Fleisch, Vegetarisch, Vegan&#10;Allergien oder Unverträglichkeiten">{{ old('questions_text', $party->questionsText()) }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Eine Frage pro Zeile, höchstens 5. Mit «|» und Kommas gibst du Antworten zur Auswahl vor, ohne «|» schreiben die Gäste frei. Die Antworten stehen in der Gästeliste und im CSV.</p>
        </div>
        <x-field name="auto_remind_days" label="Automatisch erinnern (optional)" type="number" min="1" max="14" :value="$party->auto_remind_days" class="sm:w-72" hint="So viele Tage vor der Antwortfrist (sonst vor Beginn) bekommen Gäste ohne Antwort eine Erinnerung." />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_items" value="1" @checked(old('allow_items', $party->allow_items)) class="rounded border-slate-300"> Mitbringliste (wer bringt was mit?)</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_comments" value="1" @checked(old('allow_comments', $party->allow_comments)) class="rounded border-slate-300"> Gäste dürfen Kommentare schreiben</label>
    </div>

    <div class="flex gap-3">
        <button class="btn btn-primary">Speichern</button>
        <a href="{{ $party->exists ? route('organizer.parties.show', [$organizer, $party]) : route('organizer.parties.index', $organizer) }}" class="btn btn-ghost">Abbrechen</a>
    </div>
</form>
@endsection
