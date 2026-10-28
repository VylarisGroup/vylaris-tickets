@extends('mail.layout', ['reseller' => $party->organizer->reseller, 'footer' => $party->hostLabel()])
@section('content')
<h1 style="font-size:20px;margin:0 0 12px">{{ $reminder ? 'Hast du schon geantwortet' : 'Hallo' }}, {{ $guest->name }}?</h1>
<p>{{ $reminder ? 'Wir haben noch keine endgültige Antwort von dir für' : $party->hostLabel().' lädt dich ein:' }} <strong>{{ $party->title }}</strong></p>
<p style="color:#475569">{{ $party->starts_at->translatedFormat('l, d.m.Y') }}, {{ $party->starts_at->format('H:i') }} Uhr @if ($party->venue_name)<br>{{ $party->venue_name }}@if ($party->venue_address), {{ $party->venue_address }}@endif @endif</p>
@if ($party->rsvp_deadline)<p style="color:#475569">Bitte antworte bis {{ $party->rsvp_deadline->translatedFormat('d.m.Y') }}.</p>@endif
<p><a href="{{ $url }}" style="display:inline-block;background:#1867f8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold">Einladung öffnen und antworten</a></p>
<p style="font-size:12px;color:#64748b">Dieser Link gehört nur dir. Bitte gib ihn nicht weiter.</p>
@endsection
