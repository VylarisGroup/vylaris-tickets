@extends('layouts.app')
@section('title', 'Administration')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">Plattform-Administration</h1>
@if ($update && $update['newer'])
    <a href="{{ route('admin.update') }}" class="mb-6 block rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Version {{ $update['version'] }} ist verfügbar. Jetzt aktualisieren →</a>
@endif
@if ($openReports > 0)
    <a href="{{ route('admin.reports.index') }}" class="mb-6 block rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $openReports }} offene Meldung(en) warten auf Prüfung →</a>
@endif
@if ($problems)
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <div class="mb-2 font-semibold">Einrichtung: {{ count($problems) }} Punkt(e) offen</div>
        <ul class="space-y-2">
            @foreach ($problems as $p)
                <li><span class="font-medium">{{ $p['label'] }}</span><div class="text-amber-800">{{ $p['hint'] }}</div></li>
            @endforeach
        </ul>
    </div>
@endif
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
