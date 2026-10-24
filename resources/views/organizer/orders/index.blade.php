@extends('layouts.app')
@section('title', 'Bestellungen')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
@php($money = fn ($c) => \App\Support\Money::format($c, $event->currency))
<a href="{{ route('organizer.events.show', [$organizer, $event]) }}" class="text-sm text-slate-500 hover:text-brand">← {{ $event->title }}</a>
<div class="mt-1 mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">Bestellungen</h1>
    <a href="{{ route('organizer.orders.export', array_merge([$organizer, $event], request()->query())) }}" class="btn btn-ghost">CSV-Export</a>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-4">
    <div class="card"><div class="text-sm text-slate-500">Umsatz</div><div class="text-2xl font-bold">{{ $money($stats['revenue']) }}</div><div class="text-xs text-slate-500">Online {{ $money($stats['online']) }} · Kasse {{ $money($stats['door']) }}</div></div>
    <div class="card"><div class="text-sm text-slate-500">Verkaufte Tickets</div><div class="text-2xl font-bold">{{ $stats['tickets'] }}</div><div class="text-xs text-slate-500">{{ $stats['orders'] }} Bestellungen</div></div>
    <div class="card"><div class="text-sm text-slate-500">Eingelassen</div><div class="text-2xl font-bold">{{ $stats['checked_in'] }}</div></div>
    <div class="card"><div class="text-sm text-slate-500">Offen</div><div class="text-2xl font-bold">{{ $stats['pending'] }}</div></div>
</div>

<form method="GET" class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" class="input w-64" placeholder="Name, E-Mail, Nummer">
    <select name="status" class="input w-44"><option value="">Alle Status</option>
        @foreach (['pending' => 'Offen', 'paid' => 'Bezahlt', 'cancelled' => 'Storniert', 'expired' => 'Abgelaufen', 'refunded' => 'Erstattet'] as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
    </select>
    <select name="channel" class="input w-40"><option value="">Alle Kanäle</option><option value="online" @selected(request('channel') === 'online')>Online</option><option value="door" @selected(request('channel') === 'door')>Abendkasse</option></select>
    <button class="btn btn-ghost">Filtern</button>
</form>

<div class="card divide-y divide-slate-100 p-0">
    @forelse ($orders as $o)
        <a href="{{ route('organizer.orders.show', [$organizer, $event, $o]) }}" class="flex flex-wrap items-center justify-between gap-3 px-6 py-3 hover:bg-slate-50">
            <div>
                <div class="font-medium">{{ $o->number }} · {{ $o->customer_name }}</div>
                <div class="text-sm text-slate-500">{{ $o->created_at->format('d.m.Y H:i') }} · {{ $o->channel === 'door' ? 'Abendkasse' : 'Online' }} · {{ \App\Models\Order::methodLabel($o->payment_method) }} · {{ $o->ticket_count }} Tickets</div>
            </div>
            <div class="text-right"><div class="font-semibold">{{ $o->total() }}</div><div class="text-xs text-slate-500">{{ $o->statusLabel() }}</div></div>
        </a>
    @empty
        <div class="px-6 py-6 text-sm text-slate-500">Keine Bestellungen.</div>
    @endforelse
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
