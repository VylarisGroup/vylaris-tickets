@extends('layouts.public')
@section('title', $event->title)
@section('content')
@php
    $config = [
        'types' => $types->keyBy('id'),
        'currency' => $event->currency,
        'seating' => $seatData,
        'old' => ['items' => old('items', []), 'seats' => array_values(array_map('strval', old('seats', []))), 'holders' => old('holders', [])],
        'attendees' => $event->personalize_tickets ? ['fields' => collect($event->attendeeFields())->map(fn ($k) => ['key' => $k, 'label' => \App\Support\AttendeeFields::label($k)])->values()] : null,
    ];
@endphp
@php
    $indexable = $event->status === \App\Enums\EventStatus::Published && ! $event->is_blocked && ! $event->is_private;
    $cal = app(\App\Services\CalendarExport::class);
    $desc = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $event->description))) ?: $event->title.' – '.$event->starts_at->translatedFormat('d.m.Y').($event->venue_name ? ' · '.$event->venue_name : ''), 200);
    $ld = array_filter([
        '@context' => 'https://schema.org', '@type' => 'Event', 'name' => $event->title, 'description' => $desc,
        'startDate' => $event->starts_at->toIso8601String(), 'endDate' => $event->ends_at?->toIso8601String(),
        'eventStatus' => $event->status === \App\Enums\EventStatus::Cancelled ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => $event->type === \App\Enums\EventType::Onsite ? 'https://schema.org/OfflineEventAttendanceMode' : ($event->type->isOnline() && $event->type->hasVenue() ? 'https://schema.org/MixedEventAttendanceMode' : 'https://schema.org/OnlineEventAttendanceMode'),
        'image' => $event->coverUrl() ? [$event->coverUrl()] : null,
        'location' => $event->type->hasVenue()
            ? ['@type' => 'Place', 'name' => $event->venue_name, 'address' => $event->venue_address ?: $event->city]
            : ['@type' => 'VirtualLocation', 'url' => route('shop.event', [$organizer, $event])],
        'organizer' => ['@type' => 'Organization', 'name' => $organizer->name],
        'offers' => $types->where('seated', false)->isEmpty() ? null : $types->where('seated', false)->map(fn ($t) => [
            '@type' => 'Offer', 'name' => $t['name'], 'price' => number_format($t['price_cents'] / 100, 2, '.', ''), 'priceCurrency' => $event->currency,
            'availability' => $t['sold_out'] ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock', 'url' => route('shop.event', [$organizer, $event]),
        ])->values()->all(),
    ], fn ($v) => $v !== null);
@endphp
@push('head')
    <meta name="description" content="{{ $desc }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $event->title }}">
    <meta property="og:description" content="{{ $desc }}">
    <meta property="og:url" content="{{ route('shop.event', [$organizer, $event]) }}">
    @if ($event->coverUrl())<meta property="og:image" content="{{ $event->coverUrl() }}"><meta name="twitter:card" content="summary_large_image">@endif
    @unless ($indexable)<meta name="robots" content="noindex,nofollow">@endunless
    @if ($indexable)<link rel="canonical" href="{{ route('shop.event', [$organizer, $event]) }}"><script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>@endif
@endpush
<a href="{{ route('shop.organizer', $organizer) }}" class="text-sm text-slate-500 hover:text-brand">← {{ $organizer->name }}</a>
@if ($event->coverUrl())
    <img src="{{ $event->coverUrl() }}" alt="" class="mt-3 aspect-[16/9] w-full rounded-2xl object-cover shadow-lg">
@endif
<div class="mt-4 mb-6">
    @if ($event->categoryLabel())<span class="chip mb-2">{{ $event->categoryLabel() }}</span>@endif
    <div class="text-sm font-medium text-brand">{{ $event->starts_at->translatedFormat('l, d. F Y · H:i') }} Uhr{{ $event->type !== \App\Enums\EventType::Onsite ? ' · '.$event->type->label() : '' }}</div>
    <h1 class="mt-1 text-3xl font-bold">{{ $event->title }}</h1>
    @if ($event->type->hasVenue())<p class="text-slate-600">{{ $event->venue_name }}@if ($event->venue_address), {{ $event->venue_address }}@endif</p>@endif
    @if ($event->doors_open_at)<p class="text-sm text-slate-500">Einlass ab {{ $event->doors_open_at->format('H:i') }} Uhr</p>@endif
</div>

@if ($event->status === \App\Enums\EventStatus::Draft || $event->is_blocked)
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Vorschau: Dieses Event ist nicht öffentlich sichtbar.</div>
@endif
@if ($event->status === \App\Enums\EventStatus::Cancelled)
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Diese Veranstaltung wurde abgesagt.</div>
@endif

@if ($event->description)
    <div class="card mb-6 text-slate-700">{!! nl2br(e($event->description)) !!}</div>
@endif

@if ($event->status !== \App\Enums\EventStatus::Cancelled)
    <div class="mb-6 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
        <span class="inline-flex items-center gap-1.5 text-slate-500"><x-icon name="calendar" :size="15" />Zum Kalender hinzufügen:</span>
        <a class="text-brand underline" href="{{ route('shop.calendar', [$organizer, $event]) }}">Datei (.ics)</a>
        <a class="text-brand underline" href="{{ $cal->googleUrl($event) }}" target="_blank" rel="noopener">Google Kalender</a>
    </div>
@endif

@if ($errors->has('checkout'))
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('checkout') }}</div>
@endif

@if ($isOnSale)
<form method="POST" action="{{ route('shop.order', [$organizer, $event]) }}" x-data="shop(@js($config))" @submit="checkAll($event)" class="space-y-6">
    @csrf
    <div class="card space-y-4">
        <h2 class="text-lg font-semibold">Tickets</h2>
        @foreach ($types as $t)
            @continue($t['seated'])
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-4 last:border-0 last:pb-0">
                <div>
                    <div class="font-medium">{{ $t['name'] }}</div>
                    @if ($t['description'])<div class="text-sm text-slate-500">{{ $t['description'] }}</div>@endif
                    <div class="text-sm font-semibold">{{ $t['price'] }}</div>
                </div>
                @if ($t['sold_out'])
                    <span class="text-sm font-medium text-red-600">Ausverkauft</span>
                @elseif (! $t['on_sale'])
                    <span class="text-sm text-slate-500">Nicht im Verkauf</span>
                @else
                    <select name="items[{{ $t['id'] }}]" x-model.number="items[{{ $t['id'] }}]" class="input w-24">
                        @for ($i = 0; $i <= $t['max']; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                    </select>
                @endif
            </div>
        @endforeach

        @if ($seatData)
            <div>
                <h3 class="mb-2 font-medium">Platzwahl</h3>
                <div class="space-y-3">
                    @include('shop._seat_picker')
                </div>
            </div>
        @endif

        @if ($types->where('seated', false)->isEmpty() && ! $seatData)
            <p class="text-sm text-slate-500">Für dieses Event sind aktuell keine Tickets verfügbar.</p>
        @endif
    </div>

    @if ($event->personalize_tickets)
        <div class="card space-y-4" x-show="slots.length" x-cloak>
            <div>
                <h2 class="text-lg font-semibold">Angaben zu den Personen</h2>
                <p class="text-sm text-slate-500">Jedes Ticket gilt für eine Person. Die Angaben stehen auf dem Ticket.</p>
            </div>
            {{-- Fortschritt: eine Person nach der anderen --}}
            <div x-show="slots.length > 1" class="space-y-2">
                <div class="flex items-center justify-between text-sm font-medium"><span x-text="`Person ${stepIdx + 1} von ${slots.length}`"></span><span class="text-xs font-normal text-slate-500" x-text="slots.filter((_, i) => stepDone(i)).length + ' fertig'"></span></div>
                <div class="flex gap-1.5" role="tablist" aria-label="Personen">
                    <template x-for="(s, n) in slots" :key="s.key">
                        <button type="button" role="tab" :aria-selected="n === stepIdx" :aria-label="`Person ${n + 1}`" @click="goTo(n)"
                            class="h-2 flex-1 rounded-full transition" :class="n === stepIdx ? 'bg-brand' : (stepDone(n) ? 'bg-brand/40' : 'bg-slate-200')"></button>
                    </template>
                </div>
            </div>
            <template x-for="(s, n) in slots" :key="s.key">
                <fieldset x-show="n === stepIdx" :data-slot="n" class="space-y-3 rounded-xl border border-slate-200 p-4">
                    <legend class="px-1 text-sm font-semibold" x-text="`Ticket ${n + 1} · ${s.label}`"></legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="label" :for="`h-${s.key}-name`">Name</label><input :id="`h-${s.key}-name`" :name="`holders[${s.key}][name]`" x-model="h(s.key).name" required maxlength="120" autocomplete="off" enterkeyhint="next" class="input" placeholder="Vor- und Nachname"></div>
                        <template x-for="f in attendeeFields" :key="f.key">
                            <div><label class="label" :for="`h-${s.key}-${f.key}`" x-text="f.label"></label>
                                <input :id="`h-${s.key}-${f.key}`" :name="`holders[${s.key}][${f.key}]`" x-model="h(s.key)[f.key]" required autocomplete="off" class="input"
                                    :type="f.key === 'birthdate' ? 'date' : (f.key === 'email' ? 'email' : (f.key === 'phone' ? 'tel' : 'text'))"
                                    :inputmode="f.key === 'phone' ? 'tel' : null"
                                    :max="f.key === 'birthdate' ? today : null" :min="f.key === 'birthdate' ? '1900-01-01' : null"></div>
                        </template>
                    </div>
                    <button type="button" x-show="n === 0" class="text-sm text-brand underline" @click="fillFromBuyer(s)">Meine Angaben (Name und E-Mail von unten) übernehmen</button>
                    <div class="flex gap-3 pt-1" x-show="slots.length > 1">
                        <button type="button" class="btn btn-ghost flex-1" x-show="n > 0" @click="goTo(n - 1)">Zurück</button>
                        <button type="button" class="btn btn-primary flex-1" x-show="n < slots.length - 1" @click="nextStep()">Weiter zu Person <span x-text="n + 2"></span></button>
                    </div>
                </fieldset>
            </template>
        </div>
    @endif

    <div class="card space-y-4">
        <h2 class="text-lg font-semibold">Deine Angaben</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Name" required autocomplete="name" />
            <x-field name="email" label="E-Mail" type="email" required autocomplete="email" hint="Hierhin senden wir deine Tickets." />
        </div>
        <x-field name="voucher" label="Gutscheincode (optional)" :value="old('voucher')" class="sm:w-64" />
        @if ($organizer->hasLegalLinks())
            <div>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="accept_terms" value="1" @checked(old('accept_terms')) required class="mt-0.5 rounded border-slate-300"> <span>Ich akzeptiere
                    @if ($organizer->terms_url)die <a href="{{ $organizer->terms_url }}" target="_blank" rel="noopener" class="text-brand underline">AGB</a>@endif
                    @if ($organizer->terms_url && $organizer->privacy_url) und @endif
                    @if ($organizer->privacy_url)die <a href="{{ $organizer->privacy_url }}" target="_blank" rel="noopener" class="text-brand underline">Datenschutzerklärung</a>@endif.</span></label>
                @error('accept_terms')<p class="mt-1 text-xs text-red-600">Bitte stimme den Bedingungen zu.</p>@enderror
            </div>
        @endif
    </div>

    <div class="card space-y-3">
        <h2 class="text-lg font-semibold">Zahlung</h2>
        <template x-if="total > 0">
            <div class="space-y-2">
                @forelse ($methods as $key => $label)
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand/5">
                        <input type="radio" name="method" value="{{ $key }}" @checked(old('method', array_key_first($methods)) === $key) class="text-brand">{{ $label }}
                    </label>
                @empty
                    <p class="text-sm text-red-600">Dieser Veranstalter hat noch keine Zahlungsart eingerichtet.</p>
                @endforelse
            </div>
        </template>
        <p x-show="total === 0" x-cloak class="text-sm text-slate-600">Kostenlose Tickets: keine Zahlung nötig.</p>
        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
            <div class="text-lg font-semibold">Total <span x-text="format(total)"></span></div>
            <button class="btn btn-primary" :disabled="count === 0" :class="count === 0 && 'opacity-50'">Weiter</button>
        </div>
        <p class="text-xs text-slate-500">Der angezeigte Betrag ist ohne Gutschein. Einen Rabatt ziehen wir beim Abschluss ab.</p>
    </div>
</form>
@else
    <div class="card text-slate-600">
        @if ($event->status === \App\Enums\EventStatus::Cancelled)
            Der Ticketverkauf ist beendet.
        @elseif ($event->sales_start_at && $event->sales_start_at->isFuture())
            Der Vorverkauf startet am {{ $event->sales_start_at->translatedFormat('d.m.Y \u\m H:i') }} Uhr.
        @else
            Der Vorverkauf ist beendet.@if ($event->door_sales_enabled && $event->type->hasVenue() && $event->status === \App\Enums\EventStatus::Published) Tickets gibt es evtl. an der Abendkasse.@endif
        @endif
    </div>
@endif

@php($soldOut = $types->where('sold_out', true)->where('seated', false))
@if ($isOnSale && $soldOut->isNotEmpty())
    <details class="card mt-6" @if (session('sold_out_type')) open @endif>
        <summary class="cursor-pointer font-semibold">Ausverkauft? Auf die Warteliste setzen</summary>
        <form method="POST" action="{{ route('shop.waitlist', [$organizer, $event]) }}" class="mt-4 grid gap-4 sm:grid-cols-2">@csrf
            <x-field name="name" label="Name" required />
            <x-field name="email" label="E-Mail" type="email" required />
            <div>
                <label class="label" for="wl_type">Ticketart</label>
                <select id="wl_type" name="ticket_type_id" class="input">
                    @foreach ($soldOut as $t)<option value="{{ $t['id'] }}" @selected(session('sold_out_type') == $t['id'])>{{ $t['name'] }}</option>@endforeach
                </select>
            </div>
            <x-field name="quantity" label="Anzahl" type="number" min="1" max="20" value="1" />
            <div class="sm:col-span-2"><button class="btn btn-ghost">Auf die Warteliste</button></div>
        </form>
    </details>
@endif

@if ($event->sponsors->isNotEmpty())
    <section class="mt-10">
        <h2 class="mb-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">Mit freundlicher Unterstützung</h2>
        <div class="flex flex-wrap items-center justify-center gap-3">
            @foreach ($event->sponsors as $sp)
                <div class="flex h-16 w-32 items-center justify-center rounded-xl bg-white p-2 shadow-sm">
                    @if ($sp->url)
                        <a href="{{ $sp->url }}" target="_blank" rel="noopener sponsored" class="flex h-full w-full items-center justify-center" title="{{ $sp->name }}"><img src="{{ $sp->logoUrl() }}" alt="{{ $sp->name }}" class="max-h-full max-w-full object-contain"></a>
                    @else
                        <img src="{{ $sp->logoUrl() }}" alt="{{ $sp->name }}" class="max-h-full max-w-full object-contain">
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif

<details class="mt-10 text-sm text-slate-500">
    <summary class="cursor-pointer">Event melden</summary>
    <form method="POST" action="{{ route('shop.report', [$organizer, $event]) }}" class="card mt-3 max-w-lg space-y-3">@csrf
        <div>
            <label class="label" for="reason">Grund</label>
            <select id="reason" name="reason" class="input">@foreach ($reasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        </div>
        <div><label class="label" for="message">Details</label><textarea id="message" name="message" rows="3" class="input"></textarea></div>
        <x-field name="reporter_email" label="Deine E-Mail (optional)" type="email" />
        <button class="btn btn-ghost">Meldung senden</button>
    </form>
</details>
@endsection
