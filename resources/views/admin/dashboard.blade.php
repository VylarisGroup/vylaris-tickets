@extends('layouts.app')
@section('title', 'Administration')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">Plattform-Administration</h1>
<div class="grid gap-4 sm:grid-cols-3">
    <div class="card"><div class="text-sm text-slate-500">Reseller</div><div class="text-3xl font-bold">{{ $resellerCount }}</div></div>
    <div class="card"><div class="text-sm text-slate-500">Veranstalter</div><div class="text-3xl font-bold">{{ $organizerCount }}</div></div>
    <div class="card"><div class="text-sm text-slate-500">Benutzer</div><div class="text-3xl font-bold">{{ $userCount }}</div></div>
</div>
<h2 class="mb-3 mt-8 font-semibold">Neueste Veranstalter</h2>
<div class="card divide-y divide-slate-100 p-0">
    @forelse ($latestOrganizers as $o)
        <a href="{{ route('organizer.dashboard', $o) }}" class="flex items-center justify-between px-6 py-3 hover:bg-slate-50">
            <span class="font-medium">{{ $o->name }}</span>
            <span class="text-sm text-slate-500">{{ $o->reseller?->name ?? 'Direkt' }}</span>
        </a>
    @empty
        <div class="px-6 py-4 text-sm text-slate-500">Noch keine Veranstalter.</div>
    @endforelse
</div>
@endsection
