@extends('layouts.public')
@section('content')
<section class="relative mb-12 pt-4 text-center sm:pt-8">
    <span class="chip mb-5">Vorverkauf · Abendkasse · Einlass</span>
    <h1 class="text-gradient mx-auto max-w-2xl text-4xl font-extrabold tracking-tight sm:text-6xl">{{ $brand['name'] }}</h1>
    <p class="mx-auto mt-5 max-w-xl text-base text-slate-600 sm:text-lg">Tickets für Veranstaltungen vor Ort und digital. Sicher bestellen, am Eingang einfach scannen.</p>
    @guest
        @if ($brand['reseller'] ? $brand['reseller']->allow_registration : config('tickets.allow_registration'))
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" class="btn btn-primary px-6 py-3">Als Veranstalter starten</a>
                <a href="{{ route('login') }}" class="btn btn-ghost px-6 py-3">Anmelden</a>
            </div>
        @endif
    @endguest
</section>
<form method="GET" action="{{ route('home') }}" class="mb-4 flex gap-2">
    <input type="search" name="q" value="{{ $q }}" placeholder="Event, Ort oder Stadt suchen" class="input" aria-label="Suche">
    @if ($category)<input type="hidden" name="kategorie" value="{{ $category }}">@endif
    <button class="btn btn-primary shrink-0">Suchen</button>
</form>
@if ($categories->isNotEmpty())
    <div class="mb-6 flex flex-wrap gap-2">
        <a href="{{ route('home', array_filter(['q' => $q])) }}" class="chip {{ $category === '' ? '!border-brand !text-brand' : '' }}">Alle</a>
        @foreach ($categories as $c)
            <a href="{{ route('home', array_filter(['q' => $q, 'kategorie' => $c])) }}" class="chip {{ $category === $c ? '!border-brand !text-brand' : '' }}">{{ config('tickets.categories')[$c] }}</a>
        @endforeach
    </div>
@endif
<h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">{{ $q || $category ? 'Suchergebnisse' : 'Kommende Veranstaltungen' }}</h2>
<div class="grid gap-5 sm:grid-cols-2">
    @forelse ($events as $e)
        <a href="{{ route('shop.event', [$e->organizer, $e]) }}" class="card card-link overflow-hidden !p-0">
            <div class="relative aspect-[16/9] bg-slate-100">
                @if ($e->coverUrl())
                    <img src="{{ $e->coverUrl() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand/30 via-brand/10 to-transparent text-4xl font-extrabold text-brand/60">{{ mb_strtoupper(mb_substr($e->title, 0, 1)) }}</div>
                @endif
                <div class="date-tile absolute left-3 top-3 !h-14 !w-14 backdrop-blur-md" style="background-color: color-mix(in oklab, var(--surface) 80%, transparent)">
                    <span class="text-[11px] font-semibold uppercase leading-none tracking-wide">{{ $e->starts_at->translatedFormat('M') }}</span>
                    <span class="text-xl font-extrabold leading-tight">{{ $e->starts_at->format('d') }}</span>
                </div>
                @if ($e->categoryLabel())<span class="chip absolute right-3 top-3 backdrop-blur-md">{{ $e->categoryLabel() }}</span>@endif
            </div>
            <div class="p-5">
                <div class="truncate text-lg font-semibold tracking-tight">{{ $e->title }}</div>
                <div class="mt-0.5 text-sm text-brand">{{ $e->starts_at->translatedFormat('D, H:i') }} Uhr</div>
                <div class="mt-1 truncate text-sm text-slate-500">{{ $e->organizer->name }} · {{ $e->type->hasVenue() ? ($e->city ?: $e->venue_name) : 'Online' }}</div>
            </div>
        </a>
    @empty
        <div class="card text-sm text-slate-500 sm:col-span-2">{{ $q || $category ? 'Keine passenden Veranstaltungen gefunden.' : 'Aktuell sind keine Veranstaltungen im Vorverkauf.' }}</div>
    @endforelse
</div>
@endsection
