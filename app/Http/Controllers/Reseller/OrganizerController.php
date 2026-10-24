<?php

namespace App\Http\Controllers\Reseller;

use App\Enums\OrganizerRole;
use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Reseller;
use App\Models\User;
use App\Support\Slugger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class OrganizerController extends Controller
{
    public function index(Reseller $reseller)
    {
        return view('reseller.organizers.index', [
            'reseller' => $reseller,
            'organizers' => $reseller->organizers()->withCount('users')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(Reseller $reseller)
    {
        return view('reseller.organizers.create', compact('reseller'));
    }

    public function store(Request $request, Reseller $reseller): RedirectResponse
    {
        if (! $reseller->canAddOrganizer()) {
            return back()->withInput()->withErrors(['name' => 'Das Limit an Veranstaltern ist erreicht ('.$reseller->max_organizers.').']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190'],
            'owner_password' => ['nullable', Password::min(10)],
        ]);

        DB::transaction(function () use ($data, $reseller) {
            $owner = User::where('email', $data['owner_email'])->first();

            if (! $owner) {
                abort_if(empty($data['owner_password']), 422, 'Für einen neuen Benutzer ist ein Passwort nötig.');

                $owner = User::create([
                    'name' => $data['owner_name'],
                    'email' => $data['owner_email'],
                    'password' => $data['owner_password'],
                ]);
            }

            $organizer = $reseller->organizers()->create([
                'name' => $data['name'],
                'slug' => Slugger::unique(Organizer::class, $data['name']),
                'contact_email' => $data['contact_email'] ?? $data['owner_email'],
            ]);

            $organizer->users()->attach($owner->id, ['role' => OrganizerRole::Owner->value]);
        });

        return redirect()->route('reseller.organizers.index', $reseller)->with('status', 'Veranstalter wurde angelegt.');
    }

    public function toggle(Reseller $reseller, Organizer $organizer): RedirectResponse
    {
        abort_unless($organizer->reseller_id === $reseller->id, 404);

        $organizer->update(['is_active' => ! $organizer->is_active]);

        return back()->with('status', $organizer->is_active ? 'Veranstalter aktiviert.' : 'Veranstalter gesperrt.');
    }

    public function edit(Reseller $reseller, Organizer $organizer)
    {
        abort_unless($organizer->reseller_id === $reseller->id, 404);

        return view('reseller.organizers.edit', compact('reseller', 'organizer'));
    }

    public function update(Request $request, Reseller $reseller, Organizer $organizer): RedirectResponse
    {
        abort_unless($organizer->reseller_id === $reseller->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:190'],
        ]);

        $organizer->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('reseller.organizers.index', $reseller)->with('status', 'Veranstalter wurde gespeichert.');
    }

    public function destroy(Reseller $reseller, Organizer $organizer): RedirectResponse
    {
        abort_unless($organizer->reseller_id === $reseller->id, 404);

        if ($organizer->orders()->whereIn('status', ['paid', 'pending'])->exists()) {
            return back()->withErrors(['organizer' => 'Dieser Veranstalter hat Bestellungen und kann nicht gelöscht werden. Sperre ihn stattdessen.']);
        }

        $organizer->delete();

        return back()->with('status', 'Veranstalter wurde gelöscht.');
    }
}
