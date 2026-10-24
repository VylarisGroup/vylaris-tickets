<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Eigenes Konto: Name, E-Mail und Passwort. */
class AccountController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('account.edit', [
            'user' => $user,
            'organizers' => $user->organizers()->get(),
            'resellers' => $user->resellers()->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return back()->with('status', 'Dein Konto wurde gespeichert.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ], [
            'current_password.current_password' => 'Das aktuelle Passwort stimmt nicht.',
            'password.confirmed' => 'Die Passwörter stimmen nicht überein.',
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Dein Passwort wurde geändert.');
    }
}
