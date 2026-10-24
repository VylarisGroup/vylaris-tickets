@extends('layouts.app')
@section('title', 'Events')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold">Events</h1>
    <a href="{{ route('organizer.events.create', $organizer) }}" class="btn btn-primary">Event anlegen</a>
</div>
<div class="card divide-y divide-slate-100 p-0">
    @forelse ($events as $e)
        <a href="{{ route('organizer.events.show', [$organizer, $e]) }}" class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 hover:bg-slate-50">
            <div>
                <div class="font-semibold">{{ $e->title }}</div>
                <div class="text-sm text-slate-500">{{ $e->starts_at->translatedFormat('D, d.m.Y H:i') }} · {{ $e->type->label() }}@if ($e->venue_name) · {{ $e->venue_name }}@endif</div>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $e->status->badge() }}">{{ $e->status->label() }}</span>
        </a>
    @empty
        <div class="px-6 py-6 text-sm text-slate-500">Noch keine Events. Lege dein erstes Event an.</div>
    @endforelse
</div>
@endsection
