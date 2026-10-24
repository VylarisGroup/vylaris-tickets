<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;

/** Sitzplan-Daten (Bereiche, Plätze, belegte Plätze) für Shop und Abendkasse. */
class SeatMapData
{
    private const COLORS = ['#1867f8', '#0e9f6e', '#d97706', '#9333ea', '#e11d48', '#0891b2', '#65a30d', '#c2410c'];

    public function for(Event $event): array
    {
        $sections = $event->sections()->with('ticketType')->get();
        $colors = [];
        foreach ($sections as $i => $s) {
            $colors[$s->id] = self::COLORS[$i % count(self::COLORS)];
        }

        $taken = Ticket::holding()->where('event_id', $event->id)->whereNotNull('seat_id')->pluck('seat_id')->flip();

        return [
            'sections' => $sections->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'color' => $colors[$s->id],
                'price' => $s->ticketType?->price(), 'ticket' => $s->ticketType?->name, 'price_cents' => $s->ticketType?->price_cents ?? 0,
                'sellable' => (bool) $s->ticketType?->is_active,
            ])->values(),
            'seats' => $event->seats()->where('is_blocked', false)->orderBy('id')->get()->map(fn ($s) => [
                'id' => $s->id, 'label' => $s->label, 'x' => $s->x, 'y' => $s->y, 'section' => $s->seat_section_id,
                'taken' => $taken->has($s->id),
            ])->values(),
        ];
    }

    /** Ticketarten mit Verfügbarkeit, wie sie Shop und Kasse anzeigen. */
    public function types(Event $event, bool $door = false)
    {
        return $event->ticketTypes()->where('is_active', true)->orderBy('sort')->orderBy('id')->get()->map(function ($t) use ($door) {
            $remaining = $t->remaining();

            return [
                'id' => $t->id, 'name' => $t->name, 'description' => $t->description, 'price_cents' => $t->price_cents,
                'price' => $t->price(), 'max' => $door ? ($remaining ?? 50) : min($t->max_per_order, $remaining ?? $t->max_per_order),
                'sold_out' => $remaining === 0, 'seated' => $t->isSeated(),
                'on_sale' => $door || ((! $t->sales_start_at || $t->sales_start_at->lte(now())) && (! $t->sales_end_at || $t->sales_end_at->gte(now()))),
            ];
        })->values();
    }
}
