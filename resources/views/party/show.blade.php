@extends('layouts.base')
@section('title', $party->title)
@push('head')<meta name="robots" content="noindex,nofollow,noarchive">@endpush
@section('body')
@php
    $preview = $preview ?? false;
    [$tname, $c1, $c2, $accent] = $party->palette();
    $mapUrl = ($party->venue_name || $party->venue_address) ? 'https://www.google.com/maps/search/?api=1&query='.urlencode(trim($party->venue_name.' '.$party->venue_address)) : null;
@endphp
<div class="min-h-screen" style="background:linear-gradient(160deg,{{ $c1 }}33,{{ $c2 }}22 55%,transparent)">
<main class="mx-auto max-w-xl px-4 pb-16 pt-6 sm:pt-10" style="--party:{{ $accent }}">
    <x-flash />
    @if ($preview)<div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Vorschau: So sehen deine Gäste die Einladung. Formulare sind hier gesperrt.</div>@endif

<fieldset @disabled($preview) class="contents">
    {{-- Kopf mit Titelbild oder Farbverlauf --}}
    <div class="overflow-hidden rounded-3xl shadow-xl" style="background:linear-gradient(135deg,{{ $c1 }},{{ $c2 }})">
        @if ($party->coverUrl())<img src="{{ $party->coverUrl() }}" alt="" class="h-56 w-full object-cover sm:h-72">@else<div class="h-32 sm:h-44"></div>@endif
        <div class="px-6 pb-7 pt-5 text-white">
            <p class="text-sm font-medium uppercase tracking-wider opacity-90">{{ $party->hostLabel() }} lädt dich ein</p>
            <h1 class="mt-1 text-3xl font-extrabold leading-tight sm:text-4xl" style="font-family:{{ $party->fontFamily() }}">{{ $party->title }}</h1>
            <p class="mt-3 text-sm opacity-95">Hallo {{ $guest->name }}!</p>
        </div>
    </div>

    <div class="card mt-5 space-y-3">
        <div class="flex items-start gap-3"><span class="mt-0.5 text-slate-400"><x-icon name="calendar" :size="18" /></span>
            <div><div class="font-semibold">{{ $party->starts_at->translatedFormat('l, d. F Y') }}</div>
                <div class="text-sm text-slate-500">{{ $party->starts_at->format('H:i') }} Uhr{{ $party->ends_at ? ' bis '.($party->ends_at->isSameDay($party->starts_at) ? $party->ends_at->format('H:i') : $party->ends_at->translatedFormat('d.m. H:i')).' Uhr' : '' }}</div></div></div>
        @if ($mapUrl)
            <div class="flex items-start gap-3"><span class="mt-0.5 text-slate-400"><x-icon name="globe" :size="18" /></span>
                <div><div class="font-semibold">{{ $party->venue_name ?: $party->venue_address }}</div>
                    @if ($party->venue_name && $party->venue_address)<div class="text-sm text-slate-500">{{ $party->venue_address }}</div>@endif
                    <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="text-sm underline" style="color:var(--party)">Auf Karte zeigen</a></div></div>
        @endif
        @if ($party->description)<p class="whitespace-pre-line border-t border-slate-100 pt-3 text-sm text-slate-700">{{ $party->description }}</p>@endif
        @unless ($preview)<a href="{{ route('party.calendar', $guest->token) }}" class="inline-flex items-center gap-1.5 text-sm underline" style="color:var(--party)"><x-icon name="download" :size="14" />Im Kalender speichern</a>@endunless
    </div>

    @if ($party->invite_message)
        <div class="card mt-5"><div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Nachricht von {{ $party->hostLabel() }}</div><p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $party->invite_message }}</p></div>
    @endif

    @if ($party->schedule)
        <div class="card mt-5">
            <h2 class="mb-3 text-lg font-semibold">Ablauf</h2>
            <ol class="space-y-2">
                @foreach ($party->schedule as $row)
                    <li class="flex gap-3 text-sm"><span class="w-12 shrink-0 font-semibold tabular-nums" style="color:var(--party)">{{ $row['time'] }}</span><span>{{ $row['label'] }}</span></li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- Antwort --}}
    <form method="POST" action="{{ route('party.rsvp', $guest->token) }}" class="card mt-5 space-y-4" x-data="{ r: '{{ old('response', $guest->response) }}', n: {{ (int) old('companions', $guest->companions) }}, names: @js(array_values(old('companion_names', $guest->companion_names ?? []))) }">@csrf
        <h2 class="text-lg font-semibold">{{ $guest->response ? 'Deine Antwort' : 'Kommst du?' }}</h2>
        @if ($party->rsvp_deadline && ! $party->rsvpClosed())<p class="-mt-2 text-xs text-slate-500">Bitte antworte bis {{ $party->rsvp_deadline->translatedFormat('d.m.Y') }}.</p>@endif
        @if ($party->rsvpClosed() || $party->isOver())
            <p class="text-sm text-slate-600">Die Antwortfrist ist abgelaufen.@if ($guest->response) Deine Antwort: <strong>{{ $guest->responseLabel() }}</strong>.@endif</p>
        @else
            <input type="hidden" name="response" :value="r">
            <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Antwort">
                @foreach (['yes' => 'Ich komme', 'maybe' => 'Vielleicht', 'no' => 'Ich kann nicht'] as $k => $label)
                    <button type="button" role="radio" :aria-checked="r === '{{ $k }}'" @click="r = '{{ $k }}'"
                        class="min-h-12 rounded-xl border px-2 py-3 text-sm font-semibold transition" :class="r === '{{ $k }}' ? 'border-transparent text-white shadow-md' : 'border-slate-200'"
                        :style="r === '{{ $k }}' ? 'background:var(--party)' : ''">{{ $label }}</button>
                @endforeach
            </div>
            @error('response')<p class="text-xs text-red-600">Bitte wähle eine Antwort.</p>@enderror
            @if ($party->allow_companions)
                <div x-show="r === 'yes'" x-cloak class="space-y-3">
                    <div>
                        <label class="label" for="companions">Begleitpersonen</label>
                        <select id="companions" name="companions" x-model.number="n" class="input sm:w-48">
                            @for ($i = 0; $i <= $party->max_companions; $i++)<option value="{{ $i }}">{{ $i === 0 ? 'Ich komme allein' : '+ '.$i }}</option>@endfor
                        </select>
                    </div>
                    <template x-for="i in n" :key="i">
                        <div><label class="label" :for="'cn' + i" x-text="'Name der Begleitperson ' + i"></label>
                            <input :id="'cn' + i" :name="'companion_names[' + (i - 1) + ']'" x-model="names[i - 1]" :required="r === 'yes'" maxlength="120" autocomplete="off" class="input"></div>
                    </template>
                    @error('companion_names')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @endif
            @if ($party->questions)
                <div x-show="r === 'yes' || r === 'maybe'" x-cloak class="space-y-3">
                    @foreach ($party->questions as $q)
                        <div><label class="label" for="a-{{ $q['id'] }}">{{ $q['label'] }}</label>
                            @if ($q['options'])
                                <select id="a-{{ $q['id'] }}" name="answers[{{ $q['id'] }}]" class="input"><option value="">–</option>@foreach ($q['options'] as $o)<option value="{{ $o }}" @selected(old('answers.'.$q['id'], ($guest->answers ?? [])[$q['id']] ?? null) === $o)>{{ $o }}</option>@endforeach</select>
                            @else
                                <input id="a-{{ $q['id'] }}" name="answers[{{ $q['id'] }}]" maxlength="200" value="{{ old('answers.'.$q['id'], ($guest->answers ?? [])[$q['id']] ?? '') }}" class="input">
                            @endif</div>
                    @endforeach
                </div>
            @endif
            <div><label class="label" for="message">Nachricht an den Gastgeber (optional)</label>
                <input id="message" name="message" maxlength="500" value="{{ old('message', $guest->message) }}" class="input"></div>
            <button class="btn w-full text-white" style="background:var(--party)" :disabled="!r" :class="!r && 'opacity-50'">Antwort senden</button>
        @endif
    </form>

    {{-- Wer kommt --}}
    @if ($attending->isNotEmpty() || $maybe || $declined)
        <div class="card mt-5 space-y-2">
            <h2 class="text-lg font-semibold">Wer kommt</h2>
            @if ($attending->isNotEmpty())<p class="text-sm text-slate-700">{{ $attending->pluck('name')->join(', ') }}</p>@endif
            <p class="text-xs text-slate-500">{{ $attending->count() }} zugesagt @if ($maybe) · {{ $maybe }} vielleicht @endif @if ($declined) · {{ $declined }} abgesagt @endif</p>
        </div>
    @endif

    {{-- Mitbringliste --}}
    @if ($party->allow_items)
        <div class="card mt-5 space-y-3">
            <h2 class="text-lg font-semibold">Wer bringt was mit?</h2>
            @foreach ($party->items as $i)
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span>{{ $i->label }}@if ($i->guest) <span class="text-slate-500">· {{ $i->guest_id === $guest->id ? 'du' : $i->guest->name }}</span>@endif</span>
                    @if (! $i->guest_id)
                        <form method="POST" action="{{ route('party.claim', [$guest->token, $i->id]) }}">@csrf<button class="btn btn-ghost !px-3 !py-1.5 text-xs">Ich bringe es mit</button></form>
                    @elseif ($i->guest_id === $guest->id)
                        <form method="POST" action="{{ route('party.release', [$guest->token, $i->id]) }}">@csrf @method('DELETE')<button class="btn btn-ghost !px-3 !py-1.5 text-xs">Zurückgeben</button></form>
                    @endif
                </div>
            @endforeach
            <form method="POST" action="{{ route('party.items.store', $guest->token) }}" class="flex gap-2">@csrf
                <input name="label" required maxlength="120" class="input" placeholder="Etwas anderes mitbringen …"><button class="btn btn-ghost">Eintragen</button>
            </form>
            @error('label')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    @endif

    {{-- Kommentare --}}
    @if ($party->allow_comments)
        <div class="card mt-5 space-y-3">
            <h2 class="text-lg font-semibold">Kommentare</h2>
            <form method="POST" action="{{ route('party.comment', $guest->token) }}" class="flex gap-2">@csrf
                <input name="body" required maxlength="1000" class="input" placeholder="Schreib etwas …"><button class="btn btn-ghost">Senden</button>
            </form>
            @error('body')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            @forelse ($party->comments as $c)
                <div class="text-sm"><span class="font-medium">{{ $c->author }}</span>@if ($c->by_host) <span class="text-xs" style="color:var(--party)">Gastgeber</span>@endif <span class="text-xs text-slate-400">{{ $c->created_at->format('d.m. H:i') }}</span><div class="text-slate-700">{{ $c->body }}</div></div>
            @empty
                <p class="text-sm text-slate-500">Noch keine Kommentare.</p>
            @endforelse
        </div>
    @endif

</fieldset>
    <p class="mt-8 text-center text-xs text-slate-400">Dieser Link gehört nur dir. Bitte gib ihn nicht weiter.</p>
</main>
</div>
@endsection
