<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\OrganizerRole;
use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MemberController extends Controller
{
    public function index(Organizer $organizer)
    {
        return view('organizer.members', [
            'organizer' => $organizer,
            'members' => $organizer->users()->orderBy('name')->get(),
            'roles' => OrganizerRole::cases(),
        ]);
    }

    public function store(Request $request, Organizer $organizer): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', Rule::enum(OrganizerRole::class)],
            'password' => ['nullable', Password::min(10)],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            if (empty($data['password'])) {
                return back()->withInput()->withErrors(['password' => 'Für einen neuen Benutzer ist ein Passwort nötig.']);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
        }

        if ($organizer->users()->whereKey($user->id)->exists()) {
            return back()->withInput()->withErrors(['email' => 'Dieser Benutzer gehört bereits zum Team.']);
        }

        $organizer->users()->attach($user->id, ['role' => $data['role']]);

        return back()->with('status', 'Teammitglied wurde hinzugefügt.');
    }

    public function update(Request $request, Organizer $organizer, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::enum(OrganizerRole::class)]]);

        abort_unless($organizer->users()->whereKey($user->id)->exists(), 404);

        if ($this->isLastOwner($organizer, $user) && $data['role'] !== OrganizerRole::Owner->value) {
            return back()->withErrors(['role' => 'Der letzte Inhaber kann nicht herabgestuft werden.']);
        }

        $organizer->users()->updateExistingPivot($user->id, ['role' => $data['role']]);

        return back()->with('status', 'Rolle wurde geändert.');
    }

    public function destroy(Organizer $organizer, User $user): RedirectResponse
    {
        abort_unless($organizer->users()->whereKey($user->id)->exists(), 404);

        if ($this->isLastOwner($organizer, $user)) {
            return back()->withErrors(['role' => 'Der letzte Inhaber kann nicht entfernt werden.']);
        }

        $organizer->users()->detach($user->id);

        return back()->with('status', 'Teammitglied wurde entfernt.');
    }

    private function isLastOwner(Organizer $organizer, User $user): bool
    {
        $owners = $organizer->users()->wherePivot('role', OrganizerRole::Owner->value)->pluck('users.id');

        return $owners->count() === 1 && $owners->first() === $user->id;
    }
}
