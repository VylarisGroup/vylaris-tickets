<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;

class DashboardController extends Controller
{
    public function __invoke(Reseller $reseller)
    {
        return view('reseller.dashboard', [
            'reseller' => $reseller,
            'organizers' => $reseller->organizers()->latest()->limit(8)->get(),
            'organizerCount' => $reseller->organizers()->count(),
            'eventLimit' => $reseller->max_events_per_organizer,
            'nearLimit' => $reseller->max_events_per_organizer === null ? collect() : $reseller->organizers()->withCount('events')->orderByDesc('events_count')->limit(5)->get()
                ->filter(fn ($o) => Reseller::percent($o->events_count, $reseller->max_events_per_organizer) >= 80)->values(),
            'pendingInvitations' => \App\Models\Invitation::pending()->whereHas('organizer', fn ($q) => $q->where('reseller_id', $reseller->id))->count(),
        ]);
    }
}
