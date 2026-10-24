<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketType;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketTypeController extends Controller
{
    public function store(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        $event->ticketTypes()->create($this->validated($request) + ['sort' => ($event->ticketTypes()->max('sort') ?? 0) + 1]);

        return back()->with('status', 'Ticketart wurde hinzugefügt.');
    }

    public function update(Request $request, Organizer $organizer, Event $event, TicketType $ticketType): RedirectResponse
    {
        $ticketType->update($this->validated($request));

        return back()->with('status', 'Ticketart wurde gespeichert.');
    }

    public function destroy(Organizer $organizer, Event $event, TicketType $ticketType): RedirectResponse
    {
        $ticketType->delete();

        return back()->with('status', 'Ticketart wurde gelöscht.');
    }

    private function validated(Request $request): array
    {
        // Preis akzeptiert "25", "25.50" und "25,50".
        $request->merge(['price_cents' => Money::toCents($request->input('price'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:10000000'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:100'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:sales_start_at'],
        ], ['price_cents.required' => 'Bitte einen gültigen Preis eingeben, z. B. 25.00.', 'price_cents.integer' => 'Bitte einen gültigen Preis eingeben, z. B. 25.00.']);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
