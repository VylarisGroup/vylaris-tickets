<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OrganizerRole;
use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\User;
use App\Support\Slugger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function create()
    {
        abort_unless($this->registrationOpen(), 404);

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->registrationOpen(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)],
            'organizer_name' => ['required', 'string', 'max:150'],
        ]);

        $reseller = app('tenant.reseller');

        if ($reseller && ! $reseller->canAddOrganizer()) {
            return back()->withInput()->withErrors(['organizer_name' => 'Auf dieser Plattform sind aktuell keine weiteren Veranstalter möglich.']);
        }

        $user = DB::transaction(function () use ($data, $reseller) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $organizer = Organizer::create([
                'reseller_id' => $reseller?->id,
                'name' => $data['organizer_name'],
                'slug' => Slugger::unique(Organizer::class, $data['organizer_name']),
                'contact_email' => $data['email'],
            ]);

            $organizer->users()->attach($user->id, ['role' => OrganizerRole::Owner->value]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function registrationOpen(): bool
    {
        $reseller = app('tenant.reseller');

        return $reseller ? $reseller->allow_registration : (bool) config('tickets.allow_registration');
    }
}
