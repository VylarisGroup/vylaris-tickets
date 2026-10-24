@extends('layouts.app')
@section('title', 'Teilnehmende')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<a href="{{ route('organizer.events.show', [$organizer, $event]) }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand"><x-icon name="arrow-left" :size="14" />{{ $event->title }}</a>
<div class="mb-6 mt-2 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight">Teilnehmende</h1>
        <p class="text-sm text-slate-500">{{ $checkedIn }} von {{ $total }} eingelassen</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('organizer.messages.create', [$organizer, $event]) }}" class="btn btn-ghost"><x-icon name="mail" :size="16" />Nachricht senden</a>
        <a href="{{ route('organizer.attendees.export', array_merge([$organizer, $event], request()->query())) }}" class="btn btn-ghost"><x-icon name="download" :size="16" />CSV-Export</a>
    </div>
</div>
@error('ticket')<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>@enderror

<form method="GET" class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" class="input !w-64" placeholder="Name, E-Mail, Code, Bestellung" aria-label="Suche">
    <select name="status" class="input !w-44" aria-label="Status">
        <option value="">Alle</option>
        <option value="in" @selected(request('status') === 'in')>Eingelassen</option>
        <option value="out" @selected(request('status') === 'out')>Noch nicht da</option>
    </select>
    <button class="btn btn-ghost">Filtern</button>
</form>

<div class="card divide-y divide-slate-100 p-0">
    @forelse ($tickets as $t)
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
            <div class="min-w-0">
                <div class="truncate font-medium">{{ $t->holder_name }}</div>
                <div class="truncate text-sm text-slate-500">{{ $t->ticketType->name }}@if ($t->seat) · {{ $t->seatText() }}@endif · {{ $t->order->customer_email ?: $t->order->number }}</div>
            </div>
            <div class="flex items-center gap-3">
                @if ($t->checked_in_at)<span class="chip !border-emerald-500/40 !text-emerald-600">✓ {{ $t->checked_in_at->format('H:i') }}</span>@endif
                <form method="POST" action="{{ route('organizer.attendees.toggle', [$organizer, $event, $t]) }}">@csrf @method('PATCH')
                    <button class="btn {{ $t->checked_in_at ? 'btn-ghost' : 'btn-primary' }} !px-3 !py-1.5">{{ $t->checked_in_at ? 'Zurücksetzen' : 'Einlassen' }}</button>
                </form>
            </div>
        </div>
    @empty
        <div class="px-5 py-6 text-sm text-slate-500">Keine Teilnehmenden gefunden.</div>
    @endforelse
</div>
<div class="mt-4">{{ $tickets->links() }}</div>
@endsection
