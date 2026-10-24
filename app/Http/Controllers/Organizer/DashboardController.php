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
        ]);
    }
}
