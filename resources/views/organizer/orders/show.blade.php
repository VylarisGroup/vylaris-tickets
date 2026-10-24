@extends('layouts.app')
@section('title', 'Bestellung '.$order->number)
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<a href="{{ route('organizer.orders.index', [$organizer, $event]) }}" class="text-sm text-slate-500 hover:text-brand">← Bestellungen</a>
<div class="mt-1 mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold">{{ $order->number }}</h1>
        <p class="text-sm text-slate-500">{{ $order->created_at->format('d.m.Y H:i') }} · {{ $order->channel === 'door' ? 'Abendkasse' : 'Online' }} · {{ \App\Models\Order::methodLabel($order->payment_method) }}</p>
    </div>
    <div class="text-right"><div class="text-2xl font-bold">{{ $order->total() }}</div><div class="text-sm font-medium">{{ $order->statusLabel() }}</div></div>
</div>
@error('order')<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

<div class="mb-6 flex flex-wrap gap-2">
    @if ($order->isPending() || $order->status === 'expired')
        <form method="POST" action="{{ route('organizer.orders.paid', [$organizer, $event, $order]) }}">@csrf<button class="btn btn-primary">Als bezahlt markieren</button></form>
    @endif
    @if ($order->isPending())
        <form method="POST" action="{{ route('organizer.orders.cancel', [$organizer, $event, $order]) }}" onsubmit="return confirm('Bestellung stornieren?')">@csrf<button class="btn btn-ghost">Stornieren</button></form>
    @endif
    @if ($order->isPaid())
        <form method="POST" action="{{ route('organizer.orders.refund', [$organizer, $event, $order]) }}" onsubmit="return confirm('Bestellung erstatten? Online bezahlte Beträge werden beim Zahlungsanbieter zurückgebucht.')">@csrf<button class="btn btn-danger">Erstatten</button></form>
    @endif
    @if ($order->customer_email && ($order->isPaid() || $order->isPending()))
        <form method="POST" action="{{ route('organizer.orders.resend', [$organizer, $event, $order]) }}">@csrf<button class="btn btn-ghost">E-Mail erneut senden</button></form>
    @endif
    <a href="{{ route('order.show', $order->access_token) }}" target="_blank" class="btn btn-ghost">Kundenansicht</a>
</div>

<div class="grid gap-6 md:grid-cols-2">
    <div class="card space-y-1 text-sm">
        <h2 class="mb-2 font-semibold">Kunde</h2>
        <div>{{ $order->customer_name }}</div>
        <div class="text-slate-500">{{ $order->customer_email ?: 'keine E-Mail' }}</div>
        @if ($order->creator)<div class="text-slate-500">Erfasst von {{ $order->creator->name }}</div>@endif
        @if ($order->paid_at)<div class="text-slate-500">Bezahlt am {{ $order->paid_at->format('d.m.Y H:i') }}</div>@endif
        @if ($order->expires_at && $order->isPending())<div class="text-amber-700">Reserviert bis {{ $order->expires_at->format('d.m.Y H:i') }}</div>@endif
    </div>
    <div class="card space-y-1 text-sm">
        <h2 class="mb-2 font-semibold">Beträge</h2>
        <div class="flex justify-between"><span>Zwischensumme</span><span>{{ \App\Support\Money::format($order->subtotal_cents, $order->currency) }}</span></div>
        @if ($order->discount_cents > 0)<div class="flex justify-between"><span>Rabatt {{ $order->voucher_code }}</span><span>– {{ \App\Support\Money::format($order->discount_cents, $order->currency) }}</span></div>@endif
        @if ($order->vat_cents > 0)<div class="flex justify-between text-slate-500"><span>enthaltene MWST</span><span>{{ \App\Support\Money::format($order->vat_cents, $order->currency) }}</span></div>@endif
        <div class="flex justify-between border-t border-slate-100 pt-1 font-semibold"><span>Total</span><span>{{ $order->total() }}</span></div>
    </div>
</div>

<h2 class="mt-8 mb-3 font-semibold">Tickets</h2>
<div class="card divide-y divide-slate-100 p-0 text-sm">
    @foreach ($order->tickets as $t)
        <div class="flex flex-wrap items-center justify-between gap-2 px-6 py-3">
            <div><span class="font-medium">{{ $t->ticketType->name }}</span>@if ($t->seat) · Platz {{ $t->seat->label }}@endif</div>
            <div class="text-slate-500">{{ $t->status === 'valid' ? ($t->checked_in_at ? 'Eingelöst '.$t->checked_in_at->format('d.m. H:i') : 'Gültig') : 'Ungültig' }} · {{ \App\Support\Money::format($t->price_cents, $order->currency) }}</div>
        </div>
    @endforeach
</div>
@endsection
