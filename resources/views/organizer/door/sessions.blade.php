@extends('layouts.app')
@section('title', 'Kassenschichten')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<a href="{{ route('organizer.door.show', [$organizer, $event]) }}" class="text-sm text-slate-500 hover:text-brand">← Kasse</a>
<h1 class="mt-1 mb-6 text-2xl font-bold">Kassenschichten: {{ $event->title }}</h1>
<div class="card divide-y divide-slate-100 p-0">
    @forelse ($sessions as $s)
        <a href="{{ route('organizer.door.report', [$organizer, $event, $s]) }}" class="flex items-center justify-between px-6 py-3 hover:bg-slate-50">
            <div><div class="font-medium">{{ $s->user->name }}</div><div class="text-sm text-slate-500">{{ $s->opened_at->format('d.m.Y H:i') }} – {{ $s->closed_at?->format('H:i') ?? 'offen' }}</div></div>
            <span class="text-sm {{ $s->isOpen() ? 'text-amber-700' : 'text-slate-500' }}">{{ $s->isOpen() ? 'Offen' : 'Abgeschlossen' }}</span>
        </a>
    @empty
        <div class="px-6 py-4 text-sm text-slate-500">Noch keine Schichten.</div>
    @endforelse
</div>
@endsection
