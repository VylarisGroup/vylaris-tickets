<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Organizer $organizer)
    {
        return view('organizer.dashboard', [
            'organizer' => $organizer,
            'canManage' => $request->user()->canManageOrganizer($organizer),
            'memberCount' => $organizer->users()->count(),
            'upcoming' => $organizer->events()->whereIn('status', ['published', 'draft'])->where('starts_at', '>=', now()->subDay())->orderBy('starts_at')->limit(5)->get(),
            'revenue' => (int) $organizer->orders()->where('status', 'paid')->sum('total_cents'),
            'ticketsSold' => \App\Models\Ticket::whereHas('order', fn ($q) => $q->where('organizer_id', $organizer->id)->where('status', 'paid'))->where('status', 'valid')->count(),
        ]);
    }
}
