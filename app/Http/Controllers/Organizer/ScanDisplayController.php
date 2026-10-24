<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\ScanLog;
use App\Models\ScanPairing;
use App\Services\CheckinService;
use App\Services\TicketPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Einlass-Anzeige am PC; ein gekoppeltes Smartphone dient nur als Kamera. */
class ScanDisplayController extends Controller
{
    public function show(Request $request, Organizer $organizer, Event $event, CheckinService $checkin, TicketPdf $pdf)
    {
        $pairing = ScanPairing::active()->where('event_id', $event->id)->where('user_id', $request->user()->id)->latest('id')->first();

        $recent = ScanLog::where('event_id', $event->id)->latest('id')->limit(15)->get()->reverse()->values();

        return view('organizer.scan.display', [
            'organizer' => $organizer, 'event' => $event, 'pairing' => $pairing,
            'pairUrl' => $pairing ? route('scanner.show', $pairing->token) : null,
            'qr' => $pairing ? $pdf->qr(route('scanner.show', $pairing->token)) : null,
            'config' => [
                'feed' => route('organizer.scan.display.feed', [$organizer, $event]),
                'stats' => $checkin->stats($event),
                'last' => (int) ($recent->last()?->id ?? 0),
                'recent' => $recent->map->toFeed()->all(),
                'pairingId' => $pairing?->id,
                'connected' => (bool) $pairing?->isConnected(),
            ],
        ]);
    }

    public function pair(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        // Alte Kopplungen dieses Nutzers für das Event beenden: es gibt immer nur eine aktive.
        ScanPairing::active()->where('event_id', $event->id)->where('user_id', $request->user()->id)->update(['revoked_at' => now()]);
        ScanPairing::start($event, $request->user()->id);

        return redirect()->route('organizer.scan.display', [$organizer, $event]);
    }

    public function revoke(Request $request, Organizer $organizer, Event $event, ScanPairing $pairing): RedirectResponse
    {
        abort_unless($pairing->event_id === $event->id, 404);
        $pairing->update(['revoked_at' => now()]);

        return redirect()->route('organizer.scan.display', [$organizer, $event])->with('status', 'Das Smartphone wurde getrennt.');
    }

    /** Neue Prüfungen seit der letzten bekannten ID. */
    public function feed(Request $request, Organizer $organizer, Event $event, CheckinService $checkin): JsonResponse
    {
        $after = (int) $request->query('after', 0);
        $pairing = ScanPairing::where('event_id', $event->id)->whereKey((int) $request->query('pairing'))->first();

        return response()->json([
            'logs' => ScanLog::where('event_id', $event->id)->where('id', '>', $after)->orderBy('id')->limit(50)->get()->map->toFeed(),
            'stats' => $checkin->stats($event),
            'connected' => (bool) $pairing?->isConnected(),
            'active' => (bool) $pairing?->isActive(),
        ]);
    }
}
