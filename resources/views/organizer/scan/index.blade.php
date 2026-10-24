@extends('layouts.app')
@section('title', 'Einlass')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<h1 class="mb-2 text-2xl font-bold">Einlass</h1>
<p class="mb-6 text-sm text-slate-600">Wähle das Event, bei dem du Tickets kontrollierst.</p>
<div class="card divide-y divide-slate-100 p-0">
    @forelse ($events as $e)
        <a href="{{ route('organizer.scan.show', [$organizer, $e]) }}" class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 hover:bg-slate-50">
            <div>
                <div class="font-semibold">{{ $e->title }}</div>
                <div class="text-sm text-slate-500">{{ $e->starts_at->translatedFormat('D, d.m.Y H:i') }} · {{ $e->venue_name }}</div>
            </div>
            <span class="btn btn-primary">Scanner öffnen</span>
        </a>
    @empty
        <div class="px-6 py-6 text-sm text-slate-500">Keine veröffentlichten Events vor Ort.</div>
    @endforelse
</div>
@endsection
