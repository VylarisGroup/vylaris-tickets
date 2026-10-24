<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Assistent: legt Event und Ticketarten in einem Durchlauf an. */
class WizardController extends Controller
{
    public function show(Organizer $organizer)
    {
        return view('organizer.wizard', ['organizer' => $organizer]);
    }

    public function store(Request $request, Organizer $organizer, EventController $events): RedirectResponse
    {
        $limit = $organizer->reseller?->max_events_per_organizer;

        if ($limit !== null && $organizer->events()->count() >= $limit) {
            return back()->withInput()->withErrors(['title' => "Das Limit von {$limit} Events ist erreicht."]);
        }

        $data = $events->validated($request);

        // Ticketarten: Preis akzeptiert "25", "25.50" und "25,50".
        $rows = collect($request->input('tickets', []))->filter(fn ($t) => is_array($t))->values()
            ->map(function (array $t) {
                $t['price_cents'] = Money::toCents($t['price'] ?? null);

                return $t;
            });
        $request->merge(['tickets' => $rows->all()]);

        $tickets = $request->validate([
            'tickets' => ['required', 'array', 'min:1', 'max:20'],
            'tickets.*.name' => ['required', 'string', 'max:120'],
            'tickets.*.description' => ['nullable', 'string', 'max:255'],
            'tickets.*.price_cents' => ['required', 'integer', 'min:0', 'max:10000000'],
            'tickets.*.quota' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'tickets.*.max_per_order' => ['required', 'integer', 'min:1', 'max:100'],
            'tickets.*.sales_start_at' => ['nullable', 'date'],
            'tickets.*.sales_end_at' => ['nullable', 'date'],
        ], [
            'tickets.required' => 'Lege mindestens eine Ticketart an.',
            'tickets.min' => 'Lege mindestens eine Ticketart an.',
            'tickets.*.name.required' => 'Jede Ticketart braucht einen Namen.',
            'tickets.*.price_cents.required' => 'Bitte bei jeder Ticketart einen gültigen Preis eingeben, z. B. 25.00 (oder 0 für gratis).',
            'tickets.*.price_cents.integer' => 'Bitte bei jeder Ticketart einen gültigen Preis eingeben, z. B. 25.00 (oder 0 für gratis).',
            'tickets.*.max_per_order.required' => 'Bitte «Max. pro Bestellung» angeben.',
        ])['tickets'];

        $publish = $request->input('publish') === '1';

        $event = DB::transaction(function () use ($organizer, $events, $request, $data, $tickets, $publish) {
            $data['slug'] = $events->uniqueSlug($organizer, $data['title']);
            $data = $events->handleCover($request, $data);
            $event = $organizer->events()->create($data + ['status' => ($publish ? EventStatus::Published : EventStatus::Draft)->value]);

            foreach ($tickets as $i => $t) {
                $event->ticketTypes()->create([
                    'name' => $t['name'], 'description' => $t['description'] ?? null, 'price_cents' => $t['price_cents'],
                    'quota' => $t['quota'] ?? null, 'max_per_order' => $t['max_per_order'],
                    'sales_start_at' => $t['sales_start_at'] ?? null, 'sales_end_at' => $t['sales_end_at'] ?? null,
                    'sort' => $i + 1, 'is_active' => true,
                ]);
            }

            return $event;
        });

        return redirect()->route('organizer.events.show', [$organizer, $event])
            ->with('status', $publish ? 'Dein Event ist veröffentlicht und im Vorverkauf.' : 'Dein Event wurde als Entwurf angelegt. Prüfe alles und veröffentliche es, wenn du bereit bist.');
    }
}
