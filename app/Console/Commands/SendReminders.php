<?php

namespace App\Console\Commands;

use App\Mail\EventReminderMail;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendReminders extends Command
{
    protected $signature = 'tickets:send-reminders';

    protected $description = 'Schickt Käufern kurz vor dem Event eine Erinnerung (gemäss Einstellung des Veranstalters)';

    public function handle(): int
    {
        $sent = 0;

        Event::with('organizer')->where('status', 'published')->where('is_blocked', false)
            ->whereNull('reminder_sent_at')->where('starts_at', '>', now())
            ->whereHas('organizer', fn ($q) => $q->where('is_active', true)->whereNotNull('reminder_hours'))
            ->each(function (Event $event) use (&$sent) {
                if ($event->starts_at->gt(now()->addHours((int) $event->organizer->reminder_hours))) {
                    return;
                }

                $event->orders()->with('event.organizer')->where('status', 'paid')->whereNotNull('customer_email')->whereNull('reminder_sent_at')
                    ->each(function ($order) use (&$sent) {
                        try {
                            Mail::to($order->customer_email)->send(new EventReminderMail($order));
                            $order->forceFill(['reminder_sent_at' => now()])->save();
                            $sent++;
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    });

                $event->forceFill(['reminder_sent_at' => now()])->save();
            });

        $this->info("$sent Erinnerung(en) gesendet.");

        return self::SUCCESS;
    }
}
