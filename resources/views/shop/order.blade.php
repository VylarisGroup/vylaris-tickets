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

    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
        <span class="inline-flex items-center gap-1.5 text-slate-500"><x-icon name="calendar" :size="15" />Zum Kalender hinzufügen:</span>
        <a class="text-brand underline" href="{{ route('shop.calendar', [$organizer, $event, 'zugang' => $event->access_key ?: null]) }}">Datei (.ics)</a>
        <a class="text-brand underline" href="{{ app(\App\Services\CalendarExport::class)->googleUrl($event) }}" target="_blank" rel="noopener">Google Kalender</a>
    </div>

    @if ($event->type->hasVenue())
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($tickets as $ticket)
                <article class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="px-5 py-4 text-white" style="background: linear-gradient(135deg, {{ $organizer->ticketColor() }}, #0f172a 160%)">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[11px] uppercase tracking-widest opacity-80">{{ $organizer->name }}</div>
                                <div class="mt-0.5 text-lg font-extrabold leading-tight">{{ $event->title }}</div>
                            </div>
                            @if ($organizer->logoUrl())<div class="shrink-0 rounded-lg bg-white p-1.5"><img src="{{ $organizer->logoUrl() }}" alt="" class="h-8 w-auto max-w-24 object-contain"></div>@endif
                        </div>
                        <div class="mt-1 text-xs opacity-90">{{ $event->starts_at->translatedFormat('D, d.m.Y · H:i') }} Uhr</div>
                    </header>
                    <div class="relative px-5 py-4 text-left text-sm">
                        <div class="grid grid-cols-2 gap-3">
                            <div><div class="text-[10px] uppercase tracking-wider text-slate-500">Ticket</div><div class="font-semibold">{{ $ticket->ticketType->name }}</div></div>
                            <div><div class="text-[10px] uppercase tracking-wider text-slate-500">Platz</div><div class="font-semibold">{{ $ticket->seat ? $ticket->seat->section->name.', '.$ticket->seat->label : 'Freie Platzwahl' }}</div></div>
                            @if ($event->venue_name)<div class="col-span-2"><div class="text-[10px] uppercase tracking-wider text-slate-500">Ort</div><div class="font-semibold">{{ $event->venue_name }}</div></div>@endif
                        </div>
                    </div>
                    @if ($organizer->ticket_note)<div class="border-t border-slate-200 px-5 py-3 text-left text-xs text-slate-500">{{ $organizer->ticket_note }}</div>@endif
                    <div class="relative border-t-2 border-dashed border-slate-200 px-5 pb-5 pt-4 text-center">
                        <span class="absolute -left-3 -top-3 h-6 w-6 rounded-full bg-[var(--color-page,#f1f5f9)]"></span>
                        <span class="absolute -right-3 -top-3 h-6 w-6 rounded-full bg-[var(--color-page,#f1f5f9)]"></span>
                        <div class="mx-auto inline-block rounded-xl bg-white p-2"><img src="{{ $qr($ticket->code) }}" alt="QR-Code" class="h-44 w-44"></div>
                        <div class="mt-1 font-mono text-xs tracking-widest text-slate-500">{{ $ticket->code }}</div>
                        @if ($ticket->checked_in_at)<div class="mt-2 text-xs font-medium text-amber-600">Eingelöst am {{ $ticket->checked_in_at->format('d.m.Y H:i') }}</div>@endif
                    </div>
                </article>
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
