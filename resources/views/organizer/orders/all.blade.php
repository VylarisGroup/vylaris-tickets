@extends('layouts.app')
@section('title', 'Bestellungen')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-extrabold tracking-tight">Bestellungen</h1>
<form method="GET" class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" class="input !w-64" placeholder="Name, E-Mail, Nummer" aria-label="Suche">
    <select name="event" class="input !w-56" aria-label="Event"><option value="">Alle Events</option>
        @foreach ($events as $e)<option value="{{ $e->slug }}" @selected(request('event') === $e->slug)>{{ $e->title }}</option>@endforeach
    </select>
    <select name="status" class="input !w-44" aria-label="Status"><option value="">Alle Status</option>
        @foreach (['pending' => 'Offen', 'paid' => 'Bezahlt', 'cancelled' => 'Storniert', 'expired' => 'Abgelaufen', 'refunded' => 'Erstattet'] as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
    </select>
    <button class="btn btn-ghost">Filtern</button>
</form>
<div class="card divide-y divide-slate-100 p-0">
    @forelse ($orders as $o)
        <a href="{{ route('organizer.orders.show', [$organizer, $o->event, $o]) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 transition-colors hover:bg-slate-50">
            <div class="min-w-0">
                <div class="truncate font-medium">{{ $o->number }} · {{ $o->customer_name }}</div>
                <div class="truncate text-sm text-slate-500">{{ $o->event->title }} · {{ $o->created_at->format('d.m.Y H:i') }} · {{ $o->channel === 'door' ? 'Abendkasse' : 'Online' }} · {{ $o->ticket_count }} Tickets</div>
            </div>
            <div class="text-right"><div class="font-semibold">{{ $o->total() }}</div><div class="text-xs text-slate-500">{{ $o->statusLabel() }}</div></div>
        </a>
    @empty
        <div class="px-5 py-6 text-sm text-slate-500">Keine Bestellungen gefunden.</div>
    @endforelse
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
