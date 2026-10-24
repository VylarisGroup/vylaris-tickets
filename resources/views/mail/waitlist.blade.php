@extends('mail.layout', ['reseller' => $event->organizer->reseller, 'footer' => $event->organizer->name])
@section('content')
<h1 style="font-size:20px;margin:0 0 12px">Gute Neuigkeiten, {{ $entry->name }}!</h1>
<p>Für <strong>{{ $event->title }}</strong> sind wieder Tickets verfügbar. Die Plätze sind nicht reserviert. Wer zuerst bestellt, bekommt sie.</p>
<p><a href="{{ $url }}" style="display:inline-block;background:#1867f8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold">Jetzt Tickets sichern</a></p>
@endsection
