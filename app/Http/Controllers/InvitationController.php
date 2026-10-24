<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/** Annahme einer Einladung: Der Eingeladene legt sein Passwort fest und ist danach angemeldet. */
class InvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = $this->usable($token);

        return view('auth.invitation', [
            'invitation' => $invitation,
            'organizer' => $invitation->organizer,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->usable($token);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        // Hat sich inzwischen jemand mit dieser Adresse registriert, wird nur die Zuordnung ergänzt.
        $existing = User::where('email', $invitation->email)->first();

        $user = DB::transaction(function () use ($invitation, $data, $existing) {
            $user = $existing ?? User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
            ]);

            if (! $existing) {
                // Die Adresse wurde per E-Mail bestätigt.
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $invitation->organizer->users()->syncWithoutDetaching([$user->id => ['role' => $invitation->role]]);
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        if ($existing) {
            return redirect()->route('login')->with('status', 'Du wurdest dem Veranstalter hinzugefügt. Bitte melde dich mit deinem bestehenden Konto an.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('organizer.dashboard', $invitation->organizer)->with('status', 'Willkommen! Dein Konto ist eingerichtet.');
    }

    private function usable(string $token): Invitation
    {
        $invitation = Invitation::findByToken($token);
        abort_unless($invitation && $invitation->isUsable(), 404, 'Diese Einladung ist ungültig oder abgelaufen.');

        return $invitation;
    }
}
