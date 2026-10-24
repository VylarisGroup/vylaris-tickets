@extends('layouts.app')
@section('title', $reseller->name)
@section('subnav') @include('reseller._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">{{ $reseller->name }}</h1>
<div class="grid gap-4 sm:grid-cols-2">
    <div class="card"><div class="text-sm text-slate-500">Veranstalter</div>
        <div class="text-3xl font-bold">{{ $organizerCount }}@if ($reseller->max_organizers !== null)<span class="text-lg font-normal text-slate-400"> / {{ $reseller->max_organizers }}</span>@endif</div></div>
    <div class="card"><div class="text-sm text-slate-500">Adresse</div>
        <div class="font-medium">{{ $reseller->domain ?? '–' }}</div></div>
</div>
<h2 class="mb-3 mt-8 font-semibold">Neueste Veranstalter</h2>
<div class="card divide-y divide-slate-100 p-0">
    @forelse ($organizers as $o)
        <a href="{{ route('organizer.dashboard', $o) }}" class="block px-6 py-3 font-medium hover:bg-slate-50">{{ $o->name }}</a>
    @empty
        <div class="px-6 py-4 text-sm text-slate-500">Noch keine Veranstalter. <a class="text-brand" href="{{ route('reseller.organizers.create', $reseller) }}">Ersten Veranstalter anlegen</a></div>
    @endforelse
</div>
@endsection
