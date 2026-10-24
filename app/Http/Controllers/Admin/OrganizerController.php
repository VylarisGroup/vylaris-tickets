<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;

class OrganizerController extends Controller
{
    public function index()
    {
        return view('admin.organizers', [
            'organizers' => Organizer::with('reseller')->orderBy('name')->paginate(25),
        ]);
    }
}
