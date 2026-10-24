<?php

namespace App\Http\Controllers\Scanner;

use App\Http\Controllers\Controller;
use App\Models\ScanPairing;
use App\Services\CheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Smartphone als Kamera-Scanner. Zugriff ausschliesslich über das Token der Kopplung (kein Login nötig). */
class RemoteScannerController extends Controller
{
    public function entry()
    {
        return view('scanner.entry');
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'Bitte den 6-stelligen Code eingeben.']);

        $pairing = ScanPairing::active()->where('code', $data['code'])->first();
        if (! $pairing) {
            return back()->withInput()->withErrors(['code' => 'Dieser Code ist ungültig oder abgelaufen.']);
        }

        return redirect()->route('scanner.show', $pairing->token);
    }

    public function show(string $token)
    {
        $pairing = $this->pairing($token);
        $pairing->forceFill(['last_seen_at' => now()])->save();
        $event = $pairing->event;

        return view('scanner.phone', [
            'event' => $event,
            'config' => [
                'stats' => app(CheckinService::class)->stats($event),
                'canUndo' => false,
                'urls' => ['check' => route('scanner.check', $token), 'ping' => route('scanner.ping', $token)],
            ],
        ]);
    }

    public function check(Request $request, string $token, CheckinService $checkin): JsonResponse
    {
        $pairing = $this->pairing($token);
        $data = $request->validate(['code' => ['required', 'string', 'max:200'], 'mode' => ['nullable', 'in:in,out']]);
        $pairing->forceFill(['last_seen_at' => now()])->save();

        return response()->json($checkin->checkCode($pairing->event, $data['code'], $pairing->user_id, $pairing->id, $data['mode'] ?? 'in'));
    }

    public function ping(string $token): JsonResponse
    {
        $pairing = $this->pairing($token);
        $pairing->forceFill(['last_seen_at' => now()])->save();

        return response()->json(['ok' => true, 'stats' => app(CheckinService::class)->stats($pairing->event)]);
    }

    private function pairing(string $token): ScanPairing
    {
        $pairing = ScanPairing::with('event')->where('token', $token)->first();
        abort_unless($pairing && $pairing->isActive(), 404, 'Diese Kopplung ist abgelaufen oder wurde beendet.');

        return $pairing;
    }
}
