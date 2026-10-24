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
        ]);
    }
}
