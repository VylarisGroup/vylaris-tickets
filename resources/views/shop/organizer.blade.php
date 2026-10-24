@extends('layouts.public')
@section('title', $organizer->name)
@section('content')
<h1 class="mb-6 text-3xl font-bold">{{ $organizer->name }}</h1>
<div class="space-y-4">
    @forelse ($events as $e)
        <a href="{{ route('shop.event', [$organizer, $e]) }}" class="card card-link">
            <div class="text-sm font-medium text-brand">{{ $e->starts_at->translatedFormat('l, d. F Y · H:i') }}</div>
            <div class="mt-1 text-lg font-semibold">{{ $e->title }}</div>
            <div class="text-sm text-slate-500">{{ $e->type->hasVenue() ? $e->venue_name : 'Online' }}</div>
        </a>
    @empty
        <div class="card text-sm text-slate-500">Zurzeit sind keine Veranstaltungen geplant.</div>
    @endforelse
</div>
@endsection
