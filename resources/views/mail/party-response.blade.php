@extends('mail.layout', ['reseller' => $party->organizer->reseller, 'footer' => $party->hostLabel()])
@section('content')
<h1 style="font-size:20px;margin:0 0 12px">Danke für deine Antwort, {{ $guest->name }}!</h1>
<p>Du hast für <strong>{{ $party->title }}</strong> geantwortet: <strong>{{ $guest->responseLabel() }}</strong>@if ($guest->response === 'yes' && $guest->companions), mit {{ $guest->companions }} Begleitperson{{ $guest->companions === 1 ? '' : 'en' }}@endif.</p>
<p style="color:#475569">{{ $party->starts_at->translatedFormat('l, d.m.Y') }}, {{ $party->starts_at->format('H:i') }} Uhr @if ($party->venue_name)<br>{{ $party->venue_name }}@if ($party->venue_address), {{ $party->venue_address }}@endif @endif</p>
@if (in_array($guest->response, ['yes', 'maybe'], true))<p style="color:#475569;font-size:13px">Die Kalender-Datei ist angehängt.</p>@endif
<p><a href="{{ $url }}" style="display:inline-block;background:#1867f8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold">Antwort ändern</a></p>
<p style="font-size:12px;color:#64748b">Dieser Link gehört nur dir. Bitte gib ihn nicht weiter.</p>
@endsection
