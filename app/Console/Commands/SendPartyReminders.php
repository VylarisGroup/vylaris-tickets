<?php

namespace App\Console\Commands;

use App\Mail\PartyInvitationMail;
use App\Models\Party;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendPartyReminders extends Command
{
    protected $signature = 'tickets:send-party-reminders';

    protected $description = 'Erinnert eingeladene Gäste ohne Antwort automatisch vor Antwortfrist bzw. Beginn';

    public function handle(): int
    {
        $sent = 0;

        Party::with('organizer.reseller')->whereNotNull('auto_remind_days')
            ->whereHas('organizer', fn ($q) => $q->where('is_active', true))
            ->each(function (Party $party) use (&$sent) {
                $target = $party->rsvp_deadline ?? $party->starts_at;
                if ($target->isPast() || $target->gt(now()->addDays((int) $party->auto_remind_days))) {
                    return;
                }
                $party->guests()->whereNotNull('email')->whereNotNull('invited_at')->whereNull('response')->whereNull('auto_reminded_at')
                    ->each(function ($guest) use ($party, &$sent) {
                        try {
                            Mail::to($guest->email)->send(new PartyInvitationMail($party, $guest, true));
                            $guest->update(['auto_reminded_at' => now()]);
                            $sent++;
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    });
            });

        $this->info("$sent Erinnerung(en) gesendet.");

        return self::SUCCESS;
    }
}
