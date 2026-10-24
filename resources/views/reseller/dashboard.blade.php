@extends('layouts.app')
@section('title', $reseller->name)
@section('subnav') @include('reseller._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">{{ $reseller->name }}</h1>
<div class="grid gap-4 sm:grid-cols-2">
    <div class="card space-y-4"><div class="text-sm font-semibold">Limits</div>
        @include('reseller._meter', ['label' => 'Veranstalter', 'used' => $organizerCount, 'max' => $reseller->max_organizers])
        @if ($eventLimit !== null)<div class="text-xs text-slate-500">Pro Veranstalter sind bis zu {{ $eventLimit }} Events möglich.</div>@endif
        @if ($pendingInvitations)<div class="text-xs text-slate-500">{{ $pendingInvitations }} offene Einladung{{ $pendingInvitations === 1 ? '' : 'en' }}.</div>@endif</div>
    <div class="card"><div class="text-sm text-slate-500">Adresse</div>
        <div class="font-medium">{{ $reseller->domain ?? '–' }}</div></div>
</div>
@if ($nearLimit->isNotEmpty())
    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
        <div class="font-medium">Event-Limit fast erreicht</div>
        <ul class="mt-1 list-disc pl-5">@foreach ($nearLimit as $o)<li>{{ $o->name }}: {{ $o->events_count }} / {{ $eventLimit }} Events</li>@endforeach</ul>
    </div>
@endif
<h2 class="mb-3 mt-8 font-semibold">Neueste Veranstalter</h2>
<div class="card divide-y divide-slate-100 p-0">
    @forelse ($organizers as $o)
        <a href="{{ route('organizer.dashboard', $o) }}" class="block px-6 py-3 font-medium hover:bg-slate-50">{{ $o->name }}</a>
    @empty
        <div class="px-6 py-4 text-sm text-slate-500">Noch keine Veranstalter. <a class="text-brand" href="{{ route('reseller.organizers.create', $reseller) }}">Ersten Veranstalter anlegen</a></div>
    @endforelse
</div>
@endsection
