@extends('layouts.app')
@section('title', 'Kasse öffnen')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<a href="{{ route('organizer.door.index', $organizer) }}" class="text-sm text-slate-500 hover:text-brand">← Abendkasse</a>
<h1 class="mt-1 mb-1 text-2xl font-bold">{{ $event->title }}</h1>
<p class="mb-6 text-sm text-slate-500">{{ $event->starts_at->translatedFormat('l, d.m.Y H:i') }} Uhr</p>
<form method="POST" action="{{ route('organizer.door.open', [$organizer, $event]) }}" class="card max-w-md space-y-4">@csrf
    <h2 class="text-lg font-semibold">Kasse öffnen</h2>
    <p class="text-sm text-slate-600">Jede Kassenschicht wird einzeln abgerechnet. Gib den Bargeld-Anfangsbestand (Wechselgeld) ein.</p>
    <x-field name="opening_float" :label="'Anfangsbestand ('.$event->currency.')'" value="0.00" inputmode="decimal" />
    <button class="btn btn-primary">Kasse öffnen</button>
</form>
@if ($previous->isNotEmpty())
    <p class="mt-6 text-sm"><a class="text-brand hover:underline" href="{{ route('organizer.door.sessions', [$organizer, $event]) }}">Frühere Schichten ansehen</a></p>
@endif
@endsection
