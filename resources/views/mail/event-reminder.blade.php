@extends('mail.layout', ['footer' => $event->organizer->name])
@section('content')
<h1 style="font-size:20px;margin:0 0 12px">Bald geht's los, {{ $order->customer_name }}!</h1>
<p><strong>{{ $event->title }}</strong> beginnt am {{ $event->starts_at->translatedFormat('l, d.m.Y') }} um {{ $event->starts_at->format('H:i') }} Uhr.@if ($event->doors_open_at) Einlass ab {{ $event->doors_open_at->format('H:i') }} Uhr.@endif</p>
@if ($event->type->hasVenue() && $event->venue_name)<p style="color:#475569">{{ $event->venue_name }}@if ($event->venue_address), {{ $event->venue_address }}@endif</p>@endif
@if ($event->type->isOnline() && $event->online_url)
    <p><strong>Online-Zugang:</strong> <a href="{{ $event->online_url }}">{{ $event->online_url }}</a></p>
@endif
@if ($event->organizer->ticket_note)<p style="color:#475569">{!! nl2br(e($event->organizer->ticket_note)) !!}</p>@endif
<p>Halte deine Tickets bereit (Bestellung {{ $order->number }}):</p>
<p><a href="{{ $url }}" style="display:inline-block;background:#1867f8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold">Tickets ansehen</a></p>
@endsection
