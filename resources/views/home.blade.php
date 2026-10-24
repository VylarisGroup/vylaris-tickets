@extends('layouts.public')
@section('content')
<div class="mb-8 text-center">
    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $brand['name'] }}</h1>
    <p class="mx-auto mt-3 max-w-xl text-slate-600">Tickets für Veranstaltungen vor Ort und digital. Sicher bestellen, am Eingang einfach scannen.</p>
    @guest
        @if ($brand['reseller'] ? $brand['reseller']->allow_registration : config('tickets.allow_registration'))
            <div class="mt-5 flex justify-center gap-3">
                <a href="{{ route('register') }}" class="btn btn-primary">Als Veranstalter starten</a>
                <a href="{{ route('login') }}" class="btn btn-ghost">Anmelden</a>
            </div>
        @endif
    @endguest
</div>
<h2 class="mb-3 font-semibold text-slate-700">Kommende Veranstaltungen</h2>
<div class="grid gap-4 sm:grid-cols-2">
    @forelse ($events as $e)
        <a href="{{ route('shop.event', [$e->organizer, $e]) }}" class="card block hover:border-brand">
            <div class="text-sm font-medium text-brand">{{ $e->starts_at->translatedFormat('D, d. F Y · H:i') }}</div>
            <div class="mt-1 text-lg font-semibold">{{ $e->title }}</div>
            <div class="text-sm text-slate-500">{{ $e->organizer->name }} · {{ $e->type->hasVenue() ? $e->venue_name : 'Online' }}</div>
        </a>
    @empty
        <div class="card text-sm text-slate-500 sm:col-span-2">Aktuell sind keine Veranstaltungen im Vorverkauf.</div>
    @endforelse
</div>
@endsection
