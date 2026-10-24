<?php

namespace App\Services;

use App\Mail\WaitlistMail;
use App\Models\Event;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WaitlistService
{
    /** Benachrichtigt Wartende der Reihe nach, solange Plätze frei sind. Gibt die Anzahl Mails zurück. */
    public function notifyFor(Event $event): int
    {
        if ($event->status->value !== 'published' || $event->is_blocked || ! $event->isOnSale()) {
            return 0;
        }

        $available = [];
        foreach ($event->ticketTypes()->where('is_active', true)->get() as $type) {
            $available[$type->id] = $type->isSeated() ? $this->freeSeats($event, $type->id) : $type->remaining();
        }

        $sent = 0;

        foreach ($event->waitlistEntries()->whereNull('notified_at')->orderBy('id')->get() as $entry) {
            $candidates = $entry->ticket_type_id ? [$entry->ticket_type_id] : array_keys($available);

            $match = collect($candidates)->first(fn ($id) => isset($available[$id]) && ($available[$id] === null || $available[$id] >= $entry->quantity));

            if ($match === null) {
                continue;
            }

            if ($available[$match] !== null) {
                $available[$match] -= $entry->quantity;
            }

            try {
                Mail::to($entry->email)->send(new WaitlistMail($entry->load('event.organizer')));
            } catch (\Throwable $e) {
                Log::error('Wartelisten-Mail fehlgeschlagen', ['entry' => $entry->id, 'error' => $e->getMessage()]);

                continue;
            }

            $entry->update(['notified_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    private function freeSeats(Event $event, int $ticketTypeId): int
    {
        $total = Seat::where('event_id', $event->id)->where('is_blocked', false)
            ->whereHas('section', fn ($q) => $q->where('ticket_type_id', $ticketTypeId))->count();

        $taken = Ticket::holding()->where('event_id', $event->id)->whereNotNull('seat_id')->where('ticket_type_id', $ticketTypeId)->count();

        return max(0, $total - $taken);
    }
}
