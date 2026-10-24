@extends('mail.layout', ['reseller' => $reseller, 'footer' => $organizer->name])
@section('content')
<h1 style="font-size:20px;margin:0 0 12px">Hallo {{ $invitation->name }}</h1>
<p style="line-height:1.5">Du wurdest eingeladen, den Veranstalter <strong>{{ $organizer->name }}</strong> auf {{ $reseller?->displayName() ?? config('tickets.name') }} zu verwalten. Lege jetzt dein Passwort fest, um loszulegen.</p>
<p style="margin:24px 0"><a href="{{ $url }}" style="display:inline-block;background:{{ \App\Support\MailBrand::for($reseller)['color'] }};color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold">Einladung annehmen</a></p>
<p style="font-size:13px;color:#64748b;line-height:1.5">Der Link ist {{ \App\Models\Invitation::VALID_DAYS }} Tage gültig. Falls du die Einladung nicht erwartet hast, kannst du diese E-Mail ignorieren.</p>
@endsection
