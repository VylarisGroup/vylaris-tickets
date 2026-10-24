<?php

namespace App\Http\Controllers;

use App\Models\Event;

class HomeController extends Controller
{
    public function __invoke()
    {
        $reseller = app('tenant.reseller');

        $events = Event::with('organizer')
            ->where('status', 'published')->where('is_blocked', false)
            ->where('starts_at', '>=', now()->subDay())
            ->whereHas('organizer', function ($q) use ($reseller) {
                $q->where('is_active', true);
                $reseller ? $q->where('reseller_id', $reseller->id) : $q->whereNull('reseller_id');
            })
            ->orderBy('starts_at')->limit(30)->get();

        return view('home', compact('events'));
    }
}
