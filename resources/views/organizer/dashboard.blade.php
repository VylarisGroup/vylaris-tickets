@extends('layouts.app')
@section('title', $organizer->name)
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">{{ $organizer->name }}</h1>
@unless ($organizer->is_active)
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Dieser Veranstalter ist gesperrt.</div>
@endunless
<div class="grid gap-4 sm:grid-cols-3">
    <div class="card"><div class="text-sm text-slate-500">Teammitglieder</div><div class="text-3xl font-bold">{{ $memberCount }}</div></div>
    <div class="card"><div class="text-sm text-slate-500">Währung</div><div class="text-3xl font-bold">{{ $organizer->default_currency }}</div></div>
    <div class="card"><div class="text-sm text-slate-500">MWST</div><div class="text-3xl font-bold">{{ $organizer->vat_enabled ? $organizer->vat_rate.' %' : 'aus' }}</div></div>
</div>
<div class="card mt-6 text-sm text-slate-600">Events, Ticketverkauf, Abendkasse und Einlass folgen in den nächsten Ausbaustufen.</div>
@endsection
