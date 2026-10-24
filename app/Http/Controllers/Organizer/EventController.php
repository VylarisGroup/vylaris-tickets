<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Organizer $organizer)
    {
        return view('organizer.events.index', [
            'organizer' => $organizer,
            'events' => $organizer->events()->orderByDesc('starts_at')->get(),
        ]);
    }

    public function create(Organizer $organizer)
    {
        return view('organizer.events.form', [
            'organizer' => $organizer,
            'event' => new Event([
                'type' => EventType::Onsite,
                'currency' => $organizer->default_currency,
                'door_sales_enabled' => true,
            ]),
        ]);
    }

    public function store(Request $request, Organizer $organizer): RedirectResponse
    {
        $limit = $organizer->reseller?->max_events_per_organizer;

        if ($limit !== null && $organizer->events()->count() >= $limit) {
            return back()->withInput()->withErrors(['title' => "Das Limit von {$limit} Events ist erreicht."]);
        }

        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($organizer, $data['title']);

        $event = $organizer->events()->create($data + ['status' => EventStatus::Draft->value]);

        return redirect()->route('organizer.events.show', [$organizer, $event])
            ->with('status', 'Event wurde als Entwurf angelegt. Füge jetzt Ticketarten hinzu.');
    }

    public function show(Organizer $organizer, Event $event)
    {
        return view('organizer.events.show', [
            'organizer' => $organizer,
            'event' => $event->load('ticketTypes'),
        ]);
    }

    public function edit(Organizer $organizer, Event $event)
    {
        return view('organizer.events.form', compact('organizer', 'event'));
    }

    public function update(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        $event->update($this->validated($request));

        return redirect()->route('organizer.events.show', [$organizer, $event])->with('status', 'Event wurde gespeichert.');
    }

    public function status(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(EventStatus::class)]]);

        if ($data['status'] === EventStatus::Published->value && $event->ticketTypes()->where('is_active', true)->doesntExist()) {
            return back()->withErrors(['status' => 'Zum Veröffentlichen braucht das Event mindestens eine aktive Ticketart.']);
        }

        $event->update(['status' => $data['status']]);

        return back()->with('status', 'Status: '.$event->status->label().'.');
    }

    public function destroy(Organizer $organizer, Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('organizer.events.index', $organizer)->with('status', 'Event wurde gelöscht.');
    }

    private function validated(Request $request): array
    {
        $type = EventType::tryFrom((string) $request->input('type'));

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => ['required', Rule::enum(EventType::class)],
            'venue_name' => [Rule::requiredIf($type?->hasVenue() ?? true), 'nullable', 'string', 'max:160'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'online_url' => [Rule::requiredIf($type?->isOnline() ?? false), 'nullable', 'url:https,http', 'max:500'],
            'online_instructions' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'doors_open_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:sales_start_at'],
            'currency' => ['required', Rule::in(config('tickets.currencies'))],
        ]);

        // Felder, die zum Veranstaltungstyp nicht passen, werden geleert.
        if ($type && ! $type->hasVenue()) {
            $data['venue_name'] = $data['venue_address'] = null;
        }
        if ($type && ! $type->isOnline()) {
            $data['online_url'] = $data['online_instructions'] = null;
        }

        $data['door_sales_enabled'] = $type?->hasVenue() ? $request->boolean('door_sales_enabled') : false;

        return $data;
    }

    private function uniqueSlug(Organizer $organizer, string $title): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $i = 2;

        while ($organizer->events()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
