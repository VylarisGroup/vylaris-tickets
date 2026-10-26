<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $data = $this->handleCover($request, $data);

        $event = $organizer->events()->create($data + ['status' => EventStatus::Draft->value]);

        return redirect()->route('organizer.events.show', [$organizer, $event])
            ->with('status', 'Event wurde als Entwurf angelegt. Füge jetzt Ticketarten hinzu.');
    }

    public function show(Organizer $organizer, Event $event)
    {
        return view('organizer.events.show', [
            'organizer' => $organizer,
            'event' => $event->load('ticketTypes', 'sponsors'),
        ]);
    }

    public function edit(Organizer $organizer, Event $event)
    {
        return view('organizer.events.form', compact('organizer', 'event'));
    }

    public function update(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        $data = $this->handleCover($request, $this->validated($request), $event);
        $event->update($data);

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

    public function duplicate(Organizer $organizer, Event $event): RedirectResponse
    {
        $copy = DB::transaction(function () use ($organizer, $event) {
            $copy = $event->replicate(['slug', 'status', 'is_blocked', 'blocked_reason', 'seating_mode', 'reminder_sent_at']);
            $copy->fill([
                'title' => Str::limit($event->title, 150, '').' (Kopie)',
                'slug' => $this->uniqueSlug($organizer, $event->title.' Kopie'),
                'status' => EventStatus::Draft->value,
                'seating_mode' => 'none',
            ]);
            $copy->organizer_id = $organizer->id;
            $copy->save();

            foreach ($event->ticketTypes as $t) {
                $copy->ticketTypes()->create($t->only(['name', 'description', 'price_cents', 'quota', 'max_per_order', 'sales_start_at', 'sales_end_at', 'sort', 'is_active']));
            }

            return $copy;
        });

        return redirect()->route('organizer.events.edit', [$organizer, $copy])->with('status', 'Kopie als Entwurf angelegt. Passe jetzt Datum und Details an.');
    }

    public function destroy(Organizer $organizer, Event $event): RedirectResponse
    {
        if ($event->orders()->whereIn('status', ['paid', 'pending'])->exists()) {
            return back()->withErrors(['status' => 'Dieses Event hat Bestellungen und kann nicht gelöscht werden. Sage es stattdessen ab.']);
        }

        if ($event->cover_path) {
            Storage::disk('public')->delete($event->cover_path);
        }

        foreach ($event->floors as $floor) {
            $floor->plan_path && Storage::disk('public')->delete($floor->plan_path);
        }

        foreach ($event->sponsors as $sp) {
            Storage::disk('public')->delete($sp->logo_path);
        }

        $event->delete();

        return redirect()->route('organizer.events.index', $organizer)->with('status', 'Event wurde gelöscht.');
    }

    public function validated(Request $request): array
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
            'category' => ['nullable', Rule::in(array_keys(config('tickets.categories')))],
            'city' => ['nullable', 'string', 'max:100'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'access_key' => ['nullable', 'string', 'min:4', 'max:60'],
            'attendee_fields' => ['nullable', 'array'],
            'attendee_fields.*' => [Rule::in(\App\Support\AttendeeFields::keys())],
        ]);
        unset($data['cover']);

        // Felder, die zum Veranstaltungstyp nicht passen, werden geleert.
        if ($type && ! $type->hasVenue()) {
            $data['venue_name'] = $data['venue_address'] = null;
        }
        if ($type && ! $type->isOnline()) {
            $data['online_url'] = $data['online_instructions'] = null;
        }

        $data['personalize_tickets'] = $request->boolean('personalize_tickets');
        $data['attendee_fields'] = $data['personalize_tickets'] ? array_values($data['attendee_fields'] ?? []) : null;

        $data['is_private'] = $request->boolean('is_private');
        $data['access_key'] = $data['is_private'] ? ($data['access_key'] ?? null) : null;

        $data['door_sales_enabled'] = $type?->hasVenue() ? $request->boolean('door_sales_enabled') : false;

        return $data;
    }

    /** Titelbild speichern bzw. entfernen (Checkbox «remove_cover»). */
    public function handleCover(Request $request, array $data, ?Event $event = null): array
    {
        if ($request->hasFile('cover')) {
            if ($event?->cover_path) {
                Storage::disk('public')->delete($event->cover_path);
            }
            $data['cover_path'] = $request->file('cover')->store('covers', 'public');
        } elseif ($event && $request->boolean('remove_cover') && $event->cover_path) {
            Storage::disk('public')->delete($event->cover_path);
            $data['cover_path'] = null;
        }

        return $data;
    }

    public function uniqueSlug(Organizer $organizer, string $title): string
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
