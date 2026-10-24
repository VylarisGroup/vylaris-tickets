@extends('layouts.app')
@section('title', 'Events')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-extrabold tracking-tight">Events</h1>
    <div class="flex gap-2">
        <a href="{{ route('organizer.events.create', $organizer) }}" class="btn btn-ghost">Ohne Assistent</a>
        <a href="{{ route('organizer.wizard', $organizer) }}" class="btn btn-primary"><x-icon name="wand" :size="16" />Neues Event</a>
    </div>
</div>
@if ($events->isEmpty())
    <div class="card py-12 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand/10 text-brand"><x-icon name="wand" :size="26" /></div>
        <h2 class="text-lg font-semibold">Dein erstes Event</h2>
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Der Assistent führt dich in fünf Schritten durch Event, Tickets und Veröffentlichung.</p>
        <a href="{{ route('organizer.wizard', $organizer) }}" class="btn btn-primary mt-5">Assistent starten</a>
    </div>
@else
    <div class="card divide-y divide-slate-100 p-0">
        @foreach ($events as $e)
            <a href="{{ route('organizer.events.show', [$organizer, $e]) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition-colors hover:bg-slate-50">
                <div class="flex min-w-0 items-center gap-4">
                    @if ($e->coverUrl())<img src="{{ $e->coverUrl() }}" alt="" class="h-12 w-20 shrink-0 rounded-lg object-cover">@else<div class="date-tile !h-12 !w-12 !rounded-xl"><span class="text-[10px] font-semibold uppercase leading-none">{{ $e->starts_at->translatedFormat('M') }}</span><span class="text-base font-extrabold leading-tight">{{ $e->starts_at->format('d') }}</span></div>@endif
                    <div class="min-w-0">
                        <div class="truncate font-semibold">{{ $e->title }}</div>
                        <div class="truncate text-sm text-slate-500">{{ $e->starts_at->translatedFormat('D, d.m.Y H:i') }} · {{ $e->type->label() }}@if ($e->venue_name) · {{ $e->venue_name }}@endif</div>
                    </div>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $e->status->badge() }}">{{ $e->status->label() }}</span>
            </a>
        @endforeach
    </div>
@endif
@endsection
