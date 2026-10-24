@extends('layouts.app')
@section('title', 'Event-Assistent')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
@php
    $errorStep = collect($errors->keys())->map(fn ($k) => match (true) {
        str_starts_with($k, 'tickets'), $k === 'currency' => 3,
        in_array($k, ['sales_start_at', 'sales_end_at', 'door_sales_enabled', 'publish'], true) => 4,
        in_array($k, ['starts_at', 'ends_at', 'doors_open_at', 'venue_name', 'venue_address', 'city', 'online_url', 'online_instructions'], true) => 2,
        default => 1,
    })->min() ?? 1;
    $old = collect(old())->only(['title', 'description', 'type', 'category', 'starts_at', 'ends_at', 'doors_open_at', 'venue_name', 'venue_address', 'city', 'online_url', 'online_instructions', 'currency', 'sales_start_at', 'sales_end_at', 'door_sales_enabled', 'publish'])->all();
    $oldTickets = collect(old('tickets', []))->map(fn ($t) => collect($t)->only(['name', 'description', 'price', 'quota', 'max_per_order', 'sales_start_at', 'sales_end_at'])->all())->values()->all();
    $config = ['step' => $errorStep, 'old' => $old + ['currency' => $organizer->default_currency], 'tickets' => $oldTickets, 'categories' => config('tickets.categories')];
@endphp
<div class="mx-auto max-w-3xl" x-data="eventWizard(@js($config))">
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight">Neues Event</h1>
        <p class="text-sm text-slate-500">In fünf kurzen Schritten ist dein Event samt Tickets bereit.</p>
    </div>

    {{-- Fortschritt --}}
    <ol class="mb-2 grid grid-cols-5 gap-1 text-center text-[11px] font-medium sm:text-xs" aria-label="Fortschritt">
        <template x-for="(label, i) in labels" :key="i">
            <li>
                <button type="button" class="w-full" :disabled="i + 1 > step" @click="go(i + 1)"
                        :class="i + 1 === step ? 'text-brand' : (i + 1 < step ? 'text-emerald-600' : 'text-slate-400')">
                    <span class="mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full border text-xs font-bold transition-colors"
                          :class="i + 1 === step ? 'border-brand bg-brand text-white' : (i + 1 < step ? 'border-emerald-500/40 bg-emerald-500/15' : 'border-slate-300')"
                          x-text="i + 1 < step ? '✓' : i + 1"></span>
                    <span class="hidden sm:inline" x-text="label"></span>
                </button>
            </li>
        </template>
    </ol>
    <div class="mb-6 h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-brand transition-all duration-500" :style="`width: ${progress}%`"></div></div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <div class="mb-1 font-semibold">Bitte prüfe deine Angaben:</div>
            <ul class="list-disc pl-5">@foreach (array_unique($errors->all()) as $m)<li>{{ $m }}</li>@endforeach</ul>
        </div>
    @endif
    <div x-show="error" x-cloak class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert" x-text="error"></div>

    <form method="POST" action="{{ route('organizer.wizard.store', $organizer) }}" enctype="multipart/form-data" novalidate @submit="submit($event)">@csrf

        {{-- 1 Grundlagen --}}
        <section x-ref="s1" x-show="step === 1" class="card space-y-5">
            <div>
                <h2 class="text-lg font-semibold">Worum geht es?</h2>
                <p class="text-sm text-slate-500">Titel, Art und eine kurze Beschreibung. So sehen Gäste dein Event später.</p>
            </div>
            <div>
                <label class="label" for="w-title">Titel</label>
                <input id="w-title" name="title" x-model="f.title" required maxlength="160" class="input" placeholder="z. B. Sommernachtsfest 2027" autocomplete="off">
            </div>
            <div>
                <span class="label">Art der Veranstaltung</span>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach ([['onsite', 'Vor Ort', 'Gäste kommen an einen Ort', 'calendar'], ['digital', 'Digital', 'Livestream oder Online-Meeting', 'globe'], ['hybrid', 'Hybrid', 'Vor Ort und online', 'users']] as [$v, $l, $d, $i])
                        <label class="cursor-pointer rounded-xl border p-3 transition-colors" :class="f.type === '{{ $v }}' ? 'border-brand bg-brand/10' : 'border-slate-200'">
                            <input type="radio" name="type" value="{{ $v }}" x-model="f.type" class="sr-only">
                            <div class="flex items-center gap-2 font-semibold"><x-icon :name="$i" />{{ $l }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ $d }}</div>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="w-cat">Kategorie</label>
                    <select id="w-cat" name="category" x-model="f.category" class="input">
                        <option value="">Keine</option>
                        @foreach (config('tickets.categories') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="w-cover">Titelbild (optional)</label>
                    <input id="w-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" class="input">
                </div>
            </div>
            <div>
                <label class="label" for="w-desc">Beschreibung</label>
                <textarea id="w-desc" name="description" rows="5" maxlength="10000" class="input" placeholder="Programm, Künstler, Hinweise für Gäste …">{{ old('description') }}</textarea>
            </div>
        </section>

        {{-- 2 Ort & Zeit --}}
        <section x-ref="s2" x-show="step === 2" x-cloak class="card space-y-5">
            <div>
                <h2 class="text-lg font-semibold">Wann und wo?</h2>
                <p class="text-sm text-slate-500">Beginn ist Pflicht. Ende und Einlass helfen den Gästen bei der Planung.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label class="label" for="w-start">Beginn</label><input id="w-start" type="datetime-local" name="starts_at" x-model="f.starts_at" required class="input"></div>
                <div><label class="label" for="w-end">Ende</label><input id="w-end" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="input"></div>
                <div><label class="label" for="w-doors">Einlass ab</label><input id="w-doors" type="datetime-local" name="doors_open_at" value="{{ old('doors_open_at') }}" class="input"></div>
            </div>
            <div x-show="hasVenue" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="w-venue">Name des Orts</label><input id="w-venue" name="venue_name" x-model="f.venue_name" :required="hasVenue" maxlength="160" class="input" placeholder="z. B. Kulturhaus Rössli"></div>
                    <div><label class="label" for="w-city">Stadt</label><input id="w-city" name="city" x-model="f.city" maxlength="100" class="input" placeholder="z. B. Bern"></div>
                </div>
                <div><label class="label" for="w-addr">Adresse</label><input id="w-addr" name="venue_address" value="{{ old('venue_address') }}" maxlength="255" class="input" placeholder="Strasse, PLZ Ort"></div>
            </div>
            <div x-show="isOnline" x-cloak class="space-y-4">
                <div>
                    <label class="label" for="w-url">Link zum Stream / Meeting</label>
                    <input id="w-url" type="url" name="online_url" value="{{ old('online_url') }}" :required="isOnline" maxlength="500" class="input" placeholder="https://…">
                    <p class="mt-1 text-xs text-slate-500">Wird nur an Ticketinhaber ausgeliefert, nie öffentlich angezeigt.</p>
                </div>
                <div><label class="label" for="w-instr">Hinweise für Teilnehmende</label><textarea id="w-instr" name="online_instructions" rows="3" class="input">{{ old('online_instructions') }}</textarea></div>
            </div>
        </section>

        {{-- 3 Tickets --}}
        <section x-ref="s3" x-show="step === 3" x-cloak class="space-y-4">
            <div class="card space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">Tickets</h2>
                    <p class="text-sm text-slate-500">Starte mit einer Vorlage und passe sie an, oder lege eigene Ticketarten an.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="chip" @click="preset('free')">Gratis-Event</button>
                    <button type="button" class="chip" @click="preset('standard')">Normal + Ermässigt</button>
                    <button type="button" class="chip" @click="preset('early')">Early Bird + Regulär + VIP</button>
                </div>
                <div class="sm:w-40">
                    <label class="label" for="w-cur">Währung</label>
                    <select id="w-cur" name="currency" x-model="f.currency" class="input">@foreach (config('tickets.currencies') as $c)<option>{{ $c }}</option>@endforeach</select>
                </div>
            </div>

            <template x-for="(t, i) in tickets" :key="i">
                <div class="card space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400" x-text="'Ticketart ' + (i + 1)"></div>
                        <div class="flex gap-1">
                            <button type="button" class="icon-btn !h-8 !w-8" @click="moveTicket(i, -1)" :disabled="i === 0" aria-label="Nach oben">↑</button>
                            <button type="button" class="icon-btn !h-8 !w-8" @click="moveTicket(i, 1)" :disabled="i === tickets.length - 1" aria-label="Nach unten">↓</button>
                            <button type="button" class="icon-btn !h-8 !w-8" @click="removeTicket(i)" :disabled="tickets.length === 1" aria-label="Ticketart entfernen"><x-icon name="trash" :size="15" /></button>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2"><label class="label">Name</label><input :name="`tickets[${i}][name]`" x-model="t.name" required maxlength="120" class="input" placeholder="z. B. Normal"></div>
                        <div><label class="label" x-text="`Preis (${f.currency})`"></label><input :name="`tickets[${i}][price]`" x-model="t.price" required inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,2})?" class="input" placeholder="25.00"></div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="label">Anzahl Plätze</label><input :name="`tickets[${i}][quota]`" x-model="t.quota" type="number" min="1" class="input" placeholder="unbegrenzt"></div>
                        <div><label class="label">Max. pro Bestellung</label><input :name="`tickets[${i}][max_per_order]`" x-model="t.max_per_order" type="number" min="1" max="100" required class="input"></div>
                    </div>
                    <button type="button" class="text-sm text-brand" @click="t.more = !t.more" x-text="t.more ? 'Weniger Optionen' : 'Mehr Optionen (Beschreibung, Verkaufszeitraum)'"></button>
                    <div x-show="t.more" x-cloak class="space-y-4">
                        <div><label class="label">Beschreibung</label><input :name="`tickets[${i}][description]`" x-model="t.description" maxlength="255" class="input"></div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><label class="label">Verkauf ab</label><input :name="`tickets[${i}][sales_start_at]`" x-model="t.sales_start_at" type="datetime-local" class="input"></div>
                            <div><label class="label">Verkauf bis</label><input :name="`tickets[${i}][sales_end_at]`" x-model="t.sales_end_at" type="datetime-local" class="input"></div>
                        </div>
                    </div>
                </div>
            </template>
            <button type="button" class="btn btn-ghost w-full" @click="addTicket()" :disabled="tickets.length >= 20"><x-icon name="plus" />Weitere Ticketart</button>
        </section>

        {{-- 4 Verkauf --}}
        <section x-ref="s4" x-show="step === 4" x-cloak class="card space-y-5">
            <div>
                <h2 class="text-lg font-semibold">Verkauf</h2>
                <p class="text-sm text-slate-500">Wann startet der Vorverkauf und wie soll dein Event online gehen?</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label" for="w-ss">Vorverkauf ab</label><input id="w-ss" type="datetime-local" name="sales_start_at" value="{{ old('sales_start_at') }}" class="input"><p class="mt-1 text-xs text-slate-500">Leer = sofort nach Veröffentlichung.</p></div>
                <div><label class="label" for="w-se">Vorverkauf bis</label><input id="w-se" type="datetime-local" name="sales_end_at" value="{{ old('sales_end_at') }}" class="input"><p class="mt-1 text-xs text-slate-500">Leer = bis zum Beginn.</p></div>
            </div>
            <div x-show="hasVenue">
                <input type="hidden" name="door_sales_enabled" :value="f.door_sales_enabled ? 1 : 0">
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" x-model="f.door_sales_enabled" class="mt-0.5 rounded border-slate-300"><span><span class="font-medium">Verkauf an der Abendkasse erlauben</span><br><span class="text-slate-500">Dein Team kann am Eingang Tickets verkaufen, bar oder per Karte.</span></span></label>
            </div>
            <div>
                <span class="label">Veröffentlichung</span>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="cursor-pointer rounded-xl border p-3 transition-colors" :class="f.publish === '0' ? 'border-brand bg-brand/10' : 'border-slate-200'">
                        <input type="radio" name="publish" value="0" x-model="f.publish" class="sr-only">
                        <div class="font-semibold">Als Entwurf speichern</div><div class="text-xs text-slate-500">Nur du siehst das Event. Du kannst es später veröffentlichen.</div>
                    </label>
                    <label class="cursor-pointer rounded-xl border p-3 transition-colors" :class="f.publish === '1' ? 'border-brand bg-brand/10' : 'border-slate-200'">
                        <input type="radio" name="publish" value="1" x-model="f.publish" class="sr-only">
                        <div class="font-semibold">Sofort veröffentlichen</div><div class="text-xs text-slate-500">Das Event ist sofort öffentlich und im Vorverkauf.</div>
                    </label>
                </div>
            </div>
        </section>

        {{-- 5 Fertig --}}
        <section x-ref="s5" x-show="step === 5" x-cloak class="space-y-4">
            <div class="card space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">Alles bereit?</h2>
                    <p class="text-sm text-slate-500">Prüfe die Zusammenfassung. Danach kannst du alles jederzeit noch ändern.</p>
                </div>
                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    <dt class="text-slate-500">Titel</dt><dd class="font-semibold" x-text="f.title || '–'"></dd>
                    <dt class="text-slate-500">Art</dt><dd x-text="typeLabel"></dd>
                    <dt class="text-slate-500">Kategorie</dt><dd x-text="categoryLabel"></dd>
                    <dt class="text-slate-500">Beginn</dt><dd x-text="startLabel"></dd>
                    <dt class="text-slate-500" x-show="hasVenue">Ort</dt><dd x-show="hasVenue" x-text="[f.venue_name, f.city].filter(Boolean).join(', ') || '–'"></dd>
                    <dt class="text-slate-500">Veröffentlichung</dt><dd x-text="f.publish === '1' ? 'Sofort veröffentlichen' : 'Entwurf'"></dd>
                    <dt class="text-slate-500">Plätze gesamt</dt><dd x-text="capacity === null ? 'unbegrenzt' : capacity"></dd>
                    <dt class="text-slate-500" x-show="potential !== null">Möglicher Umsatz</dt><dd x-show="potential !== null" x-text="money(potential)"></dd>
                </dl>
            </div>
            <div class="card p-0">
                <div class="border-b border-slate-200 px-5 py-3 text-sm font-semibold">Ticketarten</div>
                <ul class="divide-y divide-slate-100">
                    <template x-for="(t, i) in tickets" :key="i">
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div><div class="font-medium" x-text="t.name || '–'"></div><div class="text-xs text-slate-500" x-text="t.quota ? t.quota + ' Plätze' : 'unbegrenzt'"></div></div>
                            <div class="font-semibold" x-text="priceLabel(t)"></div>
                        </li>
                    </template>
                </ul>
            </div>
        </section>

        <div class="mt-6 flex items-center justify-between gap-3">
            <button type="button" class="btn btn-ghost" x-show="step > 1" @click="back()"><x-icon name="arrow-left" :size="16" />Zurück</button>
            <a x-show="step === 1" href="{{ route('organizer.events.index', $organizer) }}" class="btn btn-ghost">Abbrechen</a>
            <button type="button" class="btn btn-primary" x-show="step < 5" @click="next()">Weiter</button>
            <button type="submit" class="btn btn-primary" x-show="step === 5" x-cloak><x-icon name="check" :size="16" /><span x-text="f.publish === '1' ? 'Event veröffentlichen' : 'Event anlegen'"></span></button>
        </div>
    </form>
</div>
@endsection
