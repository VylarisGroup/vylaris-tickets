@extends('mail.layout', ['footer' => $event->organizer->name])
@section('content')
<p style="font-size:12px;color:#64748b;margin:0 0 8px">Nachricht zu «{{ $event->title }}» · {{ $event->starts_at->translatedFormat('d.m.Y, H:i') }} Uhr</p>
<div style="font-size:15px;line-height:1.55">{!! nl2br(e($body)) !!}</div>
<p style="margin-top:20px"><a href="{{ $url }}" style="display:inline-block;background:#1867f8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold">Zur Eventseite</a></p>
@endsection
