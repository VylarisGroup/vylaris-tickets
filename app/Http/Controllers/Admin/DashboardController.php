<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Reseller;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'resellerCount' => Reseller::count(),
            'organizerCount' => Organizer::count(),
            'userCount' => User::count(),
            'openReports' => \App\Models\Report::where('status', 'open')->count(),
            'update' => app(\App\Services\Updater::class)->cachedLatest(),
            'latestOrganizers' => Organizer::with('reseller')->latest()->limit(8)->get(),
        ]);
    }
}
