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
<h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Kommende Veranstaltungen</h2>
<div class="grid gap-4 sm:grid-cols-2">
    @forelse ($events as $e)
        <a href="{{ route('shop.event', [$e->organizer, $e]) }}" class="card card-link flex gap-4">
            <div class="date-tile">
                <span class="text-[11px] font-semibold uppercase leading-none tracking-wide">{{ $e->starts_at->translatedFormat('M') }}</span>
                <span class="text-xl font-extrabold leading-tight">{{ $e->starts_at->format('d') }}</span>
            </div>
            <div class="min-w-0">
                <div class="truncate text-lg font-semibold tracking-tight">{{ $e->title }}</div>
                <div class="mt-0.5 text-sm text-brand">{{ $e->starts_at->translatedFormat('D, H:i') }} Uhr</div>
                <div class="mt-1 truncate text-sm text-slate-500">{{ $e->organizer->name }} · {{ $e->type->hasVenue() ? $e->venue_name : 'Online' }}</div>
            </div>
        </a>
    @empty
        <div class="card text-sm text-slate-500 sm:col-span-2">Aktuell sind keine Veranstaltungen im Vorverkauf.</div>
    @endforelse
</div>
@endsection
