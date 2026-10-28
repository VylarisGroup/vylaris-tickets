@extends('mail.layout', ['reseller' => $party->organizer->reseller, 'footer' => $party->hostLabel()])
@section('content')
<h1 style="font-size:20px;margin:0 0 12px">Hallo {{ $guest->name }}</h1>
<p style="white-space:pre-line">{{ $body }}</p>
<p style="color:#475569;font-size:13px">Zu: <strong>{{ $party->title }}</strong>, {{ $party->starts_at->translatedFormat('d.m.Y, H:i') }} Uhr</p>
<p><a href="{{ $url }}" style="display:inline-block;background:#1867f8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold">Einladung öffnen</a></p>
@endsection
