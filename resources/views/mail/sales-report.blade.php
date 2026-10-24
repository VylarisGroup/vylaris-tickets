@extends('mail.layout', ['footer' => $organizer->name])
@section('content')
<h1 style="font-size:20px;margin:0 0 4px">Verkaufsreport</h1>
<p style="color:#64748b;margin:0 0 16px">{{ $organizer->name }} · {{ $periodLabel }}</p>
<table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr style="text-align:left;color:#64748b;font-size:12px"><th style="padding:6px 0">Event</th><th style="padding:6px 4px;text-align:right">Neu</th><th style="padding:6px 4px;text-align:right">Total</th><th style="padding:6px 0;text-align:right">Umsatz</th></tr>
    @foreach ($rows as $r)
        <tr style="border-top:1px solid #e2e8f0">
            <td style="padding:8px 0"><strong>{{ $r['title'] }}</strong><br><span style="color:#64748b;font-size:12px">{{ $r['date'] }}</span></td>
            <td style="padding:8px 4px;text-align:right">+{{ $r['new'] }}</td>
            <td style="padding:8px 4px;text-align:right">{{ $r['sold'] }}</td>
            <td style="padding:8px 0;text-align:right">{{ $r['revenue'] }}</td>
        </tr>
    @endforeach
</table>
<p style="margin-top:20px"><a href="{{ $url }}" style="display:inline-block;background:#1867f8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold">Statistik öffnen</a></p>
<p style="font-size:12px;color:#64748b">Report-Einstellungen findest du unter Einstellungen → Benachrichtigungen.</p>
@endsection
