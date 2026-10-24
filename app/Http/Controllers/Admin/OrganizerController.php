<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Reseller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizerController extends Controller
{
    public function index(Request $request)
    {
        $term = trim((string) $request->query('q'));

        return view('admin.organizers', [
            'organizers' => Organizer::with('reseller')->withCount(['events', 'users'])
                ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%'))
                ->orderBy('name')->paginate(25)->withQueryString(),
        ]);
    }

    public function edit(Organizer $organizer)
    {
        return view('admin.organizer-edit', ['organizer' => $organizer, 'resellers' => Reseller::orderBy('name')->get()]);
    }

    public function update(Request $request, Organizer $organizer): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'reseller_id' => ['nullable', Rule::exists('resellers', 'id')],
            'default_currency' => ['required', Rule::in(config('tickets.currencies'))],
        ]);

        $organizer->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.organizers.index')->with('status', 'Veranstalter wurde gespeichert.');
    }

    public function destroy(Organizer $organizer): RedirectResponse
    {
        if ($organizer->orders()->whereIn('status', ['paid', 'pending'])->exists()) {
            return back()->withErrors(['organizer' => 'Dieser Veranstalter hat Bestellungen und kann nicht gelöscht werden. Sperre ihn stattdessen.']);
        }

        $organizer->delete();

        return redirect()->route('admin.organizers.index')->with('status', 'Veranstalter wurde gelöscht.');
    }
}
