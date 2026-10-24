<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use App\Models\Reseller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $resellers = $user->resellers()->orderBy('name')->get();
        $organizers = $user->organizers()->orderBy('name')->get();

        // Genau ein Ziel: direkt weiterleiten.
        if (! $user->is_platform_admin && $resellers->count() + $organizers->count() === 1) {
            return $resellers->count()
                ? redirect()->route('reseller.dashboard', $resellers->first())
                : redirect()->route('organizer.dashboard', $organizers->first());
        }

        return view('dashboard', compact('resellers', 'organizers'));
    }
}
