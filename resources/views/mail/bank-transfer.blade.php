@extends('mail.layout', ['footer' => $organizer->name])
@section('content')
<h1 style="font-size:20px;margin:0 0 12px">Bitte überweise {{ $order->total() }}</h1>
<p>Danke für deine Bestellung {{ $order->number }} für <strong>{{ $event->title }}</strong>. Die Tickets reservieren wir bis {{ $order->expires_at->translatedFormat('d.m.Y') }}. Sobald deine Zahlung eingegangen ist, erhältst du die Tickets per E-Mail.</p>
<table style="border-collapse:collapse;margin:12px 0">
    <tr><td style="padding:4px 12px 4px 0;color:#64748b">Empfänger</td><td>{{ $organizer->name }}</td></tr>
    <tr><td style="padding:4px 12px 4px 0;color:#64748b">IBAN</td><td>{{ $organizer->iban }}</td></tr>
    <tr><td style="padding:4px 12px 4px 0;color:#64748b">Betrag</td><td>{{ $order->total() }}</td></tr>
    <tr><td style="padding:4px 12px 4px 0;color:#64748b">Zahlungsreferenz</td><td><strong>{{ $order->number }}</strong></td></tr>
</table>
<p><a href="{{ $url }}">Bestellung ansehen</a></p>
@endsection
