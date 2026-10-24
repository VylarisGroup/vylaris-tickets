@extends('layouts.app')
@section('title', 'Verkauf '.$order->number)
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<a href="{{ route('organizer.door.show', [$organizer, $event]) }}" class="text-sm text-slate-500 hover:text-brand">← Zurück zur Kasse</a>
<div class="mt-2 mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold">{{ $order->number }}</h1>
        <p class="text-sm text-slate-500">{{ $order->created_at->format('d.m.Y H:i') }} · {{ \App\Models\Order::methodLabel($order->payment_method) }} · <span class="font-medium">{{ $order->statusLabel() }}</span></p>
    </div>
    <div class="text-2xl font-bold">{{ $order->total() }}</div>
</div>

@if ($order->isPaid())
    <div class="mb-6 flex flex-wrap gap-3">
        <a href="{{ route('organizer.door.pdf', [$organizer, $event, $order]) }}" target="_blank" class="btn btn-primary">Tickets drucken (PDF)</a>
        <a href="{{ route('organizer.door.show', [$organizer, $event]) }}" class="btn btn-ghost">Nächster Verkauf</a>
        @if ($order->cashSession?->isOpen())
            <form method="POST" action="{{ route('organizer.door.void', [$organizer, $event, $order]) }}" onsubmit="return confirm('Verkauf stornieren? Der Betrag muss zurückgegeben werden.')">@csrf @method('DELETE')
                <button class="btn btn-danger">Verkauf stornieren</button>
            </form>
        @endif
    </div>
@else
    <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm">Dieser Verkauf wurde storniert.</div>
@endif

<div class="card divide-y divide-slate-100 p-0">
    @foreach ($order->tickets as $t)
        <div class="flex items-center justify-between px-6 py-3 text-sm {{ $t->status !== 'valid' ? 'text-slate-400 line-through' : '' }}">
            <div><span class="font-medium">{{ $t->ticketType->name }}</span>@if ($t->seat) · {{ $t->seat->label }}@endif</div>
            <div class="flex items-center gap-4"><span class="font-mono text-xs text-slate-400">{{ substr($t->code, 0, 6) }}…</span><span>{{ \App\Support\Money::format($t->price_cents, $order->currency) }}</span></div>
        </div>
    @endforeach
</div>
@if ($order->discount_cents > 0)<p class="mt-3 text-sm text-slate-600">Rabatt {{ $order->voucher_code }}: – {{ \App\Support\Money::format($order->discount_cents, $order->currency) }}</p>@endif
@endsection
