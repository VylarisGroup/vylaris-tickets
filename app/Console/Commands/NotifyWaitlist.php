<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\WaitlistService;
use Illuminate\Console\Command;

class NotifyWaitlist extends Command
{
    protected $signature = 'tickets:notify-waitlist';

    protected $description = 'Benachrichtigt Wartende, sobald wieder Plätze frei sind';

    public function handle(WaitlistService $waitlist): int
    {
        $sent = 0;

        Event::where('status', 'published')->whereHas('waitlistEntries', fn ($q) => $q->whereNull('notified_at'))
            ->each(function (Event $event) use ($waitlist, &$sent) {
                $sent += $waitlist->notifyFor($event);
            });

        $this->info("$sent Benachrichtigung(en) gesendet.");

        return self::SUCCESS;
    }
}
