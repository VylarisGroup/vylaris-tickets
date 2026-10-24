@extends('layouts.public')
@section('title', 'Bestellung '.$order->number)
@section('content')
<div class="mb-6">
    <div class="text-sm text-slate-500">Bestellung {{ $order->number }}</div>
    <h1 class="text-2xl font-bold">{{ $event->title }}</h1>
    <p class="text-slate-600">{{ $event->starts_at->translatedFormat('l, d. F Y · H:i') }} Uhr @if ($event->type->hasVenue()) · {{ $event->venue_name }}@endif</p>
</div>

@if ($order->isPaid())
    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Bezahlt. Deine Tickets sind bereit.@if ($order->customer_email) Wir haben sie dir auch an {{ $order->customer_email }} geschickt.@endif</div>

    <div class="mb-6 flex flex-wrap gap-3">
        <a href="{{ route('order.pdf', $order->access_token) }}" class="btn btn-primary" target="_blank">Tickets als PDF</a>
    </div>

    @if ($event->type->isOnline() && $event->online_url)
        <div class="card mb-6">
            <h2 class="font-semibold">Online-Zugang</h2>
            <a href="{{ $event->online_url }}" class="break-all text-brand underline" rel="noopener">{{ $event->online_url }}</a>
            @if ($event->online_instructions)<p class="mt-2 text-sm text-slate-600">{!! nl2br(e($event->online_instructions)) !!}</p>@endif
        </div>
    @endif

    @if ($event->type->hasVenue())
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($tickets as $ticket)
                <div class="card text-center">
                    <div class="font-semibold">{{ $ticket->ticketType->name }}</div>
                    <div class="text-sm text-slate-500">{{ $ticket->seat ? $ticket->seat->section->name.', '.$ticket->seat->label : 'Freie Platzwahl' }}</div>
                    <img src="{{ $qr($ticket->code) }}" alt="QR-Code" class="mx-auto my-3 h-48 w-48">
                    <div class="font-mono text-xs tracking-widest text-slate-500">{{ $ticket->code }}</div>
                    @if ($ticket->checked_in_at)<div class="mt-2 text-xs text-amber-700">Eingelöst am {{ $ticket->checked_in_at->format('d.m.Y H:i') }}</div>@endif
                </div>
            @endforeach
        </div>
    @endif
@elseif ($order->isPending())
    <div class="card space-y-3">
        <h2 class="font-semibold">Zahlung offen</h2>
        @if ($order->payment_method === 'bank')
            <p class="text-sm text-slate-600">Bitte überweise <strong>{{ $order->total() }}</strong> bis {{ $order->expires_at->translatedFormat('d.m.Y') }}. Danach erhältst du die Tickets per E-Mail und hier auf dieser Seite.</p>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                <dt class="text-slate-500">Empfänger</dt><dd>{{ $organizer->name }}</dd>
                <dt class="text-slate-500">IBAN</dt><dd class="font-mono">{{ $organizer->iban }}</dd>
                <dt class="text-slate-500">Referenz</dt><dd class="font-mono font-semibold">{{ $order->number }}</dd>
            </dl>
        @else
            <p class="text-sm text-slate-600">Deine Zahlung ist noch nicht bestätigt. Die Plätze sind bis {{ $order->expires_at->format('H:i') }} Uhr für dich reserviert.</p>
            @if ($order->payment_url)<a href="{{ $order->payment_url }}" class="btn btn-primary">Jetzt bezahlen</a>@endif
            <p class="text-xs text-slate-500">Hast du schon bezahlt? Lade diese Seite in einem Moment neu.</p>
        @endif
    </div>
@else
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        Diese Bestellung ist {{ strtolower($order->statusLabel()) }}.
        <a class="underline" href="{{ route('shop.event', [$organizer, $event]) }}">Zurück zum Event</a>
    </div>
@endif

<div class="card mt-6 text-sm">
    <div class="flex justify-between"><span class="text-slate-500">Name</span><span>{{ $order->customer_name }}</span></div>
    <div class="flex justify-between"><span class="text-slate-500">Zahlungsart</span><span>{{ \App\Models\Order::methodLabel($order->payment_method) }}</span></div>
    @if ($order->discount_cents > 0)<div class="flex justify-between"><span class="text-slate-500">Rabatt @if ($order->voucher_code)({{ $order->voucher_code }})@endif</span><span>– {{ \App\Support\Money::format($order->discount_cents, $order->currency) }}</span></div>@endif
    @if ($order->vat_cents > 0)<div class="flex justify-between"><span class="text-slate-500">davon MWST {{ $organizer->vat_rate }} %</span><span>{{ \App\Support\Money::format($order->vat_cents, $order->currency) }}</span></div>@endif
    <div class="mt-2 flex justify-between border-t border-slate-100 pt-2 font-semibold"><span>Total</span><span>{{ $order->total() }}</span></div>
</div>
@endsection
