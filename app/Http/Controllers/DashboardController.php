<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use App\Models\Reseller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $resellers = $user->resellers()->orderBy('name')->get();
        $organizers = $user->organizers()->orderBy('name')->get();

        // Standard: direkt ins zuletzt gewählte (sonst erste) Mandanten-Dashboard. «?alle=1» zeigt die Übersicht.
        if (! $request->boolean('alle')) {
            $current = (string) $request->session()->get('current_tenant');
            $target = $resellers->toBase()->map(fn ($r) => ['reseller:'.$r->id, route('reseller.dashboard', $r)])
                ->merge($organizers->toBase()->map(fn ($o) => ['organizer:'.$o->id, route('organizer.dashboard', $o)]));

            $hit = $target->firstWhere(0, $current) ?? $target->first();
            if ($hit) {
                return redirect($hit[1]);
            }

            if ($user->is_platform_admin) {
                return redirect()->route('admin.dashboard');
            }
        }

        return view('dashboard', compact('resellers', 'organizers'));
    }

    /** Mandantenwechsel: merkt sich die Wahl und öffnet das Dashboard. */
    public function switch(Request $request, string $type, int $id): RedirectResponse
    {
        $user = $request->user();

        if ($type === 'reseller') {
            $tenant = $user->resellers()->whereKey($id)->firstOrFail();
            $url = route('reseller.dashboard', $tenant);
        } else {
            abort_unless($type === 'organizer', 404);
            $tenant = $user->organizers()->whereKey($id)->firstOrFail();
            $url = route('organizer.dashboard', $tenant);
        }

        $request->session()->put('current_tenant', $type.':'.$id);

        return redirect($url);
    }
}
