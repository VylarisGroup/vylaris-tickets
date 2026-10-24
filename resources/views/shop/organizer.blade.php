@extends('layouts.public')
@section('title', $organizer->name)
@section('content')
<h1 class="mb-6 text-3xl font-extrabold tracking-tight">{{ $organizer->name }}</h1>
<div class="grid gap-5 sm:grid-cols-2">
    @forelse ($events as $e)
        <a href="{{ route('shop.event', [$organizer, $e]) }}" class="card card-link overflow-hidden !p-0">
            @if ($e->coverUrl())<img src="{{ $e->coverUrl() }}" alt="" loading="lazy" class="aspect-[16/9] w-full object-cover">@endif
            <div class="p-5">
                <div class="text-sm font-medium text-brand">{{ $e->starts_at->translatedFormat('l, d. F Y · H:i') }}</div>
                <div class="mt-1 text-lg font-semibold tracking-tight">{{ $e->title }}</div>
                <div class="text-sm text-slate-500">{{ $e->type->hasVenue() ? $e->venue_name : 'Online' }}</div>
            </div>
        </a>
    @empty
        <div class="card text-sm text-slate-500 sm:col-span-2">Zurzeit sind keine Veranstaltungen geplant.</div>
    @endforelse
</div>
@endsection
