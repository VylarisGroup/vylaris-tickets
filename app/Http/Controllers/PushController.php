<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Push\PushNotifier;
use App\Services\Push\Vapid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Push-Benachrichtigungen: Geräte anmelden/abmelden, Arten wählen, Test senden. */
class PushController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('account.notifications', [
            'user' => $user,
            'categories' => collect(User::PUSH_CATEGORIES)->filter(fn ($c) => ! $c[2] || $user->is_platform_admin),
            'devices' => $user->pushSubscriptions()->latest('id')->get(),
            'vapidKey' => Vapid::publicKey(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $prefs = [];
        foreach (array_keys(User::PUSH_CATEGORIES) as $key) {
            $prefs[$key] = $request->boolean('prefs.'.$key);
        }
        $user->forceFill(['push_prefs' => $prefs])->save();

        return back()->with('status', 'Benachrichtigungen wurden gespeichert.');
    }

    /** Gerät anmelden (Aufruf aus dem Browser nach Zustimmung). */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'endpoint' => $data['endpoint'], 'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm', 'device' => $this->deviceName($request),
            ],
        );

        return response()->json(['ok' => true]);
    }

    /** Gerät abmelden (per Endpunkt aus dem Browser). */
    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        $request->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->json(['ok' => true]);
    }

    /** Gerät aus der Liste entfernen. */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->pushSubscriptions()->whereKey($id)->delete();

        return back()->with('status', 'Gerät wurde entfernt.');
    }

    public function test(Request $request, PushNotifier $push): RedirectResponse
    {
        $sent = $push->test($request->user());

        return back()->with($sent > 0 ? 'status' : 'error_status', $sent > 0
            ? "Test gesendet an $sent Gerät(e). Es sollte gleich erscheinen."
            : 'Es ist kein Gerät angemeldet. Aktiviere zuerst die Benachrichtigungen auf diesem Gerät.');
    }

    private function deviceName(Request $request): string
    {
        $ua = (string) $request->userAgent();
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone', str_contains($ua, 'iPad') => 'iPad', str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Mac OS') => 'Mac', str_contains($ua, 'Linux') => 'Linux', default => 'Gerät',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Chrome') => 'Chrome', str_contains($ua, 'Safari') => 'Safari', default => 'Browser',
        };

        return $os.' · '.$browser;
    }
}
