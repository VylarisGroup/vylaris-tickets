@extends('layouts.app')
@section('title', 'Einladungen')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-extrabold tracking-tight">Einladungen</h1>
    <a href="{{ route('organizer.parties.create', $organizer) }}" class="btn btn-primary"><x-icon name="plus" :size="16" />Neue Einladung</a>
</div>
@if ($parties->isEmpty())
    <div class="card py-12 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand/10 text-brand"><x-icon name="mail" :size="26" /></div>
        <h2 class="text-lg font-semibold">Deine erste Einladung</h2>
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Für Feiern, Vereinsanlässe oder Treffen ohne Ticketverkauf: Gäste bekommen einen persönlichen Link und sagen zu oder ab.</p>
        <a href="{{ route('organizer.parties.create', $organizer) }}" class="btn btn-primary mt-5">Einladung erstellen</a>
    </div>
@else
    <div class="card divide-y divide-slate-100 p-0">
        @foreach ($parties as $p)
            @php($c = $p->counts())
            <a href="{{ route('organizer.parties.show', [$organizer, $p]) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition-colors hover:bg-slate-50">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl" style="background:linear-gradient(135deg,{{ $p->palette()[1] }},{{ $p->palette()[2] }})">@if ($p->coverUrl())<img src="{{ $p->coverUrl() }}" alt="" class="h-full w-full object-cover">@endif</div>
                    <div class="min-w-0">
                        <div class="truncate font-semibold">{{ $p->title }}</div>
                        <div class="truncate text-sm text-slate-500">{{ $p->starts_at->translatedFormat('D, d.m.Y H:i') }}@if ($p->venue_name) · {{ $p->venue_name }}@endif</div>
                    </div>
                </div>
                <div class="text-sm text-slate-600">{{ $c['yes_people'] }} Zusagen · {{ $c['open'] }} offen · {{ $c['total'] }} Gäste</div>
            </a>
        @endforeach
    </div>
@endif
@endsection
