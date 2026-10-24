<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8">
<style>
    @page { margin: 28px; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #0f172a; font-size: 12px; }
    .ticket { page-break-after: always; border: 2px solid {{ $color }}; border-radius: 12px; overflow: hidden; }
    .ticket:last-child { page-break-after: auto; }
    .head { background: {{ $color }}; color: #fff; padding: 16px 22px; }
    .head .brand { font-size: 11px; opacity: .85; }
    .head h1 { margin: 4px 0 0; font-size: 24px; }
    .head .when { margin-top: 6px; font-size: 12px; opacity: .9; }
    .stub { border-top: 2px dashed #94a3b8; background: #f8fafc; }
    .body { padding: 20px 22px; }
    table.meta { width: 100%; border-collapse: collapse; }
    table.meta td { padding: 6px 0; vertical-align: top; }
    .lbl { color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
    .val { font-size: 14px; font-weight: bold; }
    .qr { text-align: center; padding: 16px 0 6px; margin: 16px -22px 0; }
    .qr img { width: 210px; height: 210px; }
    .code { text-align: center; font-family: 'DejaVu Sans Mono', monospace; letter-spacing: 2px; font-size: 12px; color: #475569; }
    .foot { border-top: 1px dashed #cbd5e1; padding: 10px 22px; font-size: 10px; color: #64748b; }
</style></head>
<body>
@foreach ($tickets as $ticket)
    <div class="ticket">
        <div class="head">
            <div class="brand">{{ $organizer->name }}</div>
            <h1>{{ $event->title }}</h1>
            <div class="when">{{ $event->starts_at->translatedFormat('l, d.m.Y') }} · {{ $event->starts_at->format('H:i') }} Uhr</div>
        </div>
        <div class="body">
            <table class="meta">
                <tr>
                    <td><div class="lbl">Datum</div><div class="val">{{ $event->starts_at->translatedFormat('D, d.m.Y') }}</div></td>
                    <td><div class="lbl">Beginn</div><div class="val">{{ $event->starts_at->format('H:i') }} Uhr @if ($event->doors_open_at) <span style="font-weight:normal;font-size:10px">(Einlass {{ $event->doors_open_at->format('H:i') }})</span>@endif</div></td>
                </tr>
                <tr>
                    <td colspan="2"><div class="lbl">Ort</div><div class="val">@if ($event->type->hasVenue()){{ $event->venue_name }}@if ($event->venue_address), {{ $event->venue_address }}@endif @else Online @endif</div></td>
                </tr>
                <tr>
                    <td><div class="lbl">Ticket</div><div class="val">{{ $ticket->ticketType->name }}</div></td>
                    <td><div class="lbl">Platz</div><div class="val">@if ($ticket->seat){{ $ticket->seat->section->name }}, {{ $ticket->seat->label }}@else Freie Platzwahl @endif</div></td>
                </tr>
                <tr>
                    <td><div class="lbl">Name</div><div class="val">{{ $ticket->holder_name }}</div></td>
                    <td><div class="lbl">Preis</div><div class="val">{{ $ticket->price_cents === 0 ? 'Gratis' : \App\Support\Money::format($ticket->price_cents, $order->currency) }}</div></td>
                </tr>
            </table>
            @if ($event->type->isOnline() && $event->online_url)
                <p style="margin:12px 0 0"><span class="lbl">Online-Zugang</span><br><span class="val" style="font-size:11px">{{ $event->online_url }}</span>@if ($event->online_instructions)<br><span style="font-size:10px">{{ $event->online_instructions }}</span>@endif</p>
            @endif
            @if ($event->type->hasVenue())
                <div class="qr stub"><img src="{{ $qr($ticket->code) }}" alt="QR"></div>
                <div class="code stub" style="margin:0 -22px;padding:0 0 14px">{{ $ticket->code }}</div>
            @endif
        </div>
        <div class="foot">Bestellung {{ $order->number }} · {{ $order->customer_name }} · Jedes Ticket ist nur einmal gültig. Bitte nicht weitergeben oder fotografieren lassen.@if ($organizer->vat_enabled && $order->vat_cents > 0) · Preis inkl. {{ $organizer->vat_rate }} % MWST @if ($organizer->vat_number) ({{ $organizer->vat_number }})@endif @endif</div>
    </div>
@endforeach
</body></html>
