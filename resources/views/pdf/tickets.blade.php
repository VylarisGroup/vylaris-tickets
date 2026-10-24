<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8">
<style>
    @page { margin: 28px; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #0f172a; font-size: 11px; margin: 0; }
    .page { page-break-after: always; width: 737px; }
    .page:last-child { page-break-after: auto; }
    table { border-collapse: collapse; }
    td { vertical-align: top; padding: 0; }

    .card { border: 1px solid #dbe2ec; border-radius: 16px; overflow: hidden; background: #ffffff; }

    /* Kopf */
    .hero { width: 735px; height: 214px; background-color: {{ $dark }}; @if ($hero) background-image: url('{{ $hero }}'); background-size: 735px 214px; background-repeat: no-repeat; @endif color: #ffffff; position: relative; }
    .hero-in { padding: 20px 26px 0 26px; }
    .org-name { font-size: 10px; letter-spacing: 1.6px; text-transform: uppercase; color: #ffffff; }
    .logo-tile { background: #ffffff; border-radius: 8px; padding: 5px 8px; }
    .badge { font-size: 9px; letter-spacing: 1.4px; text-transform: uppercase; color: #ffffff; border: 1px solid #ffffff; border-radius: 20px; padding: 4px 11px; }
    .cat { margin-top: 20px; font-size: 9px; letter-spacing: 2.2px; text-transform: uppercase; color: #ffffff; }
    .title { margin-top: 4px; font-size: 27px; font-weight: bold; line-height: 1.12; color: #ffffff; }
    .when { margin-top: 9px; font-size: 13px; color: #ffffff; }

    /* Inhalt */
    .lbl { font-size: 8px; letter-spacing: 1.2px; text-transform: uppercase; color: #64748b; }
    .val { margin-top: 2px; font-size: 14px; font-weight: bold; color: #0f172a; }
    .val-sm { margin-top: 2px; font-size: 11px; color: #334155; }
    .cell { padding: 0 0 15px 0; }
    .pill { display: inline-block; background: {{ $color }}; color: #ffffff; border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: bold; }

    .stub { background: {{ $tint }}; border-left: 2px dashed #94a3b8; text-align: center; }
    .qr-box { background: #ffffff; border: 2px solid {{ $color }}; border-radius: 14px; padding: 8px; width: 186px; margin: 0 auto; }
    .qr-box img { width: 168px; height: 168px; }
    .code { margin-top: 10px; font-family: 'DejaVu Sans Mono', monospace; font-size: 11px; letter-spacing: 2px; color: #334155; }
    .scan-hint { margin-top: 4px; font-size: 9px; color: #64748b; }
    .scissors { font-size: 14px; color: #94a3b8; }

    /* Infobereich */
    .info { margin-top: 14px; background: {{ $tint }}; border: 1px solid {{ $soft }}; border-radius: 14px; }
    .info td { padding: 13px 16px; font-size: 9.5px; line-height: 1.5; color: #334155; }
    .info h3 { margin: 0 0 4px; font-size: 9px; letter-spacing: 1.3px; text-transform: uppercase; color: {{ $color }}; }
    .foot { margin-top: 12px; font-size: 8.5px; color: #64748b; line-height: 1.5; }
    .foot-line { margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 8px; color: #94a3b8; }
</style></head>
<body>
@php
    $total = $tickets->count();
    $isLive = $event->type->hasVenue();
    $place = $event->type->hasVenue() ? trim($event->venue_name.($event->venue_address ? ', '.$event->venue_address : ''), ', ') : 'Online';
    $contact = array_filter([
        $organizer->name,
        trim(($organizer->street ?: '').(($organizer->zip || $organizer->city) ? ($organizer->street ? ', ' : '').trim($organizer->zip.' '.$organizer->city) : '')),
        $organizer->contact_email,
    ]);
@endphp
@foreach ($tickets as $i => $ticket)
    <div class="page">
        <div class="card">
            <div class="hero">
                <div class="hero-in">
                    <table width="100%"><tr>
                        <td>
                            <table><tr>
                                @if ($logo)<td style="padding-right:10px"><div class="logo-tile"><img src="{{ $logo }}" alt="" style="max-height:26px;max-width:100px"></div></td>@endif
                                <td style="vertical-align:middle"><div class="org-name">{{ $organizer->name }}</div></td>
                            </tr></table>
                        </td>
                        <td align="right"><span class="badge">Ticket {{ $i + 1 }} / {{ $total }}</span></td>
                    </tr></table>
                    @if ($event->categoryLabel())<div class="cat">{{ $event->categoryLabel() }}</div>@else<div class="cat">&nbsp;</div>@endif
                    <div class="title">{{ \Illuminate\Support\Str::limit($event->title, 70) }}</div>
                    <div class="when">{{ $event->starts_at->translatedFormat('l, d. F Y') }} · {{ $event->starts_at->format('H:i') }} Uhr</div>
                </div>
            </div>

            <table width="100%" style="table-layout:fixed">
                <tr>
                    <td style="padding:22px 22px 8px 26px">
                        <table width="100%">
                            <tr>
                                <td class="cell" width="50%"><div class="lbl">Datum</div><div class="val">{{ $event->starts_at->translatedFormat('D, d.m.Y') }}</div></td>
                                <td class="cell" width="50%"><div class="lbl">Beginn</div><div class="val">{{ $event->starts_at->format('H:i') }} Uhr</div>@if ($event->doors_open_at)<div class="val-sm">Einlass ab {{ $event->doors_open_at->format('H:i') }} Uhr</div>@endif</td>
                            </tr>
                            <tr>
                                <td class="cell" colspan="2"><div class="lbl">Ort</div><div class="val">{{ $event->type->hasVenue() ? $event->venue_name : 'Online-Event' }}</div>@if ($event->type->hasVenue() && $event->venue_address)<div class="val-sm">{{ $event->venue_address }}</div>@endif</td>
                            </tr>
                            <tr>
                                <td class="cell"><div class="lbl">Ticket</div><div style="margin-top:4px"><span class="pill">{{ $ticket->ticketType->name }}</span></div></td>
                                <td class="cell"><div class="lbl">Platz</div><div class="val">@if ($ticket->seat){{ $ticket->seat->section->name }}, {{ $ticket->seat->label }}@else Freie Platzwahl @endif</div></td>
                            </tr>
                            <tr>
                                <td class="cell"><div class="lbl">Name</div><div class="val">{{ $ticket->holder_name ?: $order->customer_name }}</div></td>
                                <td class="cell"><div class="lbl">Preis</div><div class="val">{{ $ticket->price_cents === 0 ? 'Gratis' : \App\Support\Money::format($ticket->price_cents, $order->currency) }}</div></td>
                            </tr>
                        </table>
                    </td>
                    <td class="stub" width="250" style="padding:16px 18px 16px 18px">
                        <div class="scissors">&#9986;</div>
                        @if ($isLive)
                            <div class="qr-box"><img src="{{ $qr($ticket->code) }}" alt="QR-Code"></div>
                            <div class="code">{{ $ticket->code }}</div>
                            <div class="scan-hint">Am Eingang scannen lassen</div>
                        @else
                            <div style="margin-top:30px" class="lbl">Online-Zugang</div>
                            <div style="margin-top:6px;font-size:10px;font-weight:bold;word-wrap:break-word">{{ $event->online_url }}</div>
                            <div class="code">{{ $ticket->code }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="info" width="100%">
            <tr>
                <td width="34%">
                    <h3>So geht's</h3>
                    @if ($isLive)QR-Code am Eingang vorzeigen, ausgedruckt oder direkt auf dem Handy. Bitte Helligkeit hochstellen.@else Den Zugang findest du oben. Der Code gilt nur für dich.@endif
                </td>
                <td width="33%" style="border-left:1px solid {{ $soft }}">
                    <h3>Veranstalter</h3>
                    @foreach ($contact as $line){{ $line }}<br>@endforeach
                </td>
                <td width="33%" style="border-left:1px solid {{ $soft }}">
                    <h3>Gut zu wissen</h3>
                    @if ($organizer->ticket_note){{ $organizer->ticket_note }}@else Jedes Ticket ist nur einmal gültig. Bitte nicht weitergeben oder den Code fotografieren lassen. @endif
                    @if ($event->type->isOnline() && $event->online_instructions)<br>{{ \Illuminate\Support\Str::limit($event->online_instructions, 160) }}@endif
                </td>
            </tr>
        </table>

        <div class="foot-line">
            Bestellung {{ $order->number }} · {{ $order->customer_name }} · {{ $order->paid_at?->format('d.m.Y') ?? $order->created_at->format('d.m.Y') }}
            @if ($organizer->vat_enabled && $order->vat_cents > 0) · Preis inkl. {{ $organizer->vat_rate }} % MWST @if ($organizer->vat_number)({{ $organizer->vat_number }})@endif @endif
            @if ($organizer->terms_url) · AGB: {{ $organizer->terms_url }}@endif
            <span style="float:right">{{ $brandName }}</span>
        </div>
    </div>
@endforeach
</body></html>
