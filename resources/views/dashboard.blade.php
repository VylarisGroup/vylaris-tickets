@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<h1 class="mb-6 text-2xl font-bold">Willkommen, {{ auth()->user()->name }}</h1>
@can('platform-admin')
    <a href="{{ route('admin.dashboard') }}" class="card card-link">
        <div class="font-semibold">Plattform-Administration</div>
        <div class="text-sm text-slate-500">Reseller, Veranstalter und Benutzer verwalten</div>
    </a>
@endcan
@if ($resellers->isNotEmpty())
    <h2 class="mb-3 mt-8 font-semibold text-slate-700">Reseller</h2>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($resellers as $r)
            <a href="{{ route('reseller.dashboard', $r) }}" class="card card-link"><div class="font-semibold">{{ $r->name }}</div><div class="text-sm text-slate-500">{{ \App\Enums\ResellerRole::from($r->pivot->role)->label() }}</div></a>
        @endforeach
    </div>
@endif
@if ($organizers->isNotEmpty())
    <h2 class="mb-3 mt-8 font-semibold text-slate-700">Veranstalter</h2>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($organizers as $o)
            <a href="{{ route('organizer.dashboard', $o) }}" class="card card-link"><div class="font-semibold">{{ $o->name }}</div><div class="text-sm text-slate-500">{{ \App\Enums\OrganizerRole::from($o->pivot->role)->label() }}</div></a>
        @endforeach
    </div>
@endif
@if (! auth()->user()->is_platform_admin && $resellers->isEmpty() && $organizers->isEmpty())
    <div class="card text-slate-600">Dein Konto ist noch keinem Veranstalter zugeordnet. Bitte melde dich bei deinem Ansprechpartner.</div>
@endif
@endsection
