<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSponsor;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Sponsoren-Logos, die auf der öffentlichen Eventseite erscheinen. */
class SponsorController extends Controller
{
    public function store(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'sponsor_name' => ['required', 'string', 'max:120'],
            'sponsor_url' => ['nullable', 'url:https,http', 'max:500'],
            'sponsor_logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($event->sponsors()->count() >= 12) {
            return back()->withErrors(['sponsor_name' => 'Maximal 12 Sponsoren pro Event.']);
        }

        $event->sponsors()->create([
            'name' => $data['sponsor_name'], 'url' => $data['sponsor_url'] ?? null,
            'logo_path' => $request->file('sponsor_logo')->store('sponsors/'.$event->id, 'public'),
            'sort' => ((int) $event->sponsors()->max('sort')) + 1,
        ]);

        return back()->with('status', 'Sponsor wurde hinzugefügt.');
    }

    public function destroy(Organizer $organizer, Event $event, EventSponsor $sponsor): RedirectResponse
    {
        abort_unless($sponsor->event_id === $event->id, 404);
        Storage::disk('public')->delete($sponsor->logo_path);
        $sponsor->delete();

        return back()->with('status', 'Sponsor wurde entfernt.');
    }
}
