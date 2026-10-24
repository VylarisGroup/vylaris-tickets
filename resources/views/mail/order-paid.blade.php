@extends('mail.layout', ['reseller' => $event->organizer->reseller, 'footer' => $event->organizer->name])
@section('content')
<h1 style="font-size:20px;margin:0 0 12px">Danke für deine Bestellung, {{ $order->customer_name }}!</h1>
<p>Deine Tickets für <strong>{{ $event->title }}</strong> sind im Anhang als PDF. Zeige am Eingang den QR-Code auf dem Handy oder ausgedruckt vor.</p>
<p style="color:#475569">{{ $event->starts_at->translatedFormat('l, d.m.Y, H:i') }} Uhr @if ($event->venue_name)<br>{{ $event->venue_name }}@if ($event->venue_address), {{ $event->venue_address }}@endif @endif</p>
@if ($event->type->isOnline() && $event->online_url)
    <p><strong>Online-Zugang:</strong> <a href="{{ $event->online_url }}">{{ $event->online_url }}</a>@if ($event->online_instructions)<br>{!! nl2br(e($event->online_instructions)) !!}@endif</p>
@endif
<p>Bestellnummer: <strong>{{ $order->number }}</strong><br>Total: {{ $order->total() }}</p>
<p><a href="{{ $url }}" style="display:inline-block;background:#1867f8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold">Tickets online ansehen</a></p>
@endsection
