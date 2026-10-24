<?php

namespace App\Console\Commands;

use App\Mail\SalesReportMail;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Ticket;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSalesReports extends Command
{
    protected $signature = 'tickets:send-reports';

    protected $description = 'Verkaufsreport per E-Mail (täglich oder montags wöchentlich, gemäss Einstellung)';

    public function handle(): int
    {
        $sent = 0;

        Organizer::where('is_active', true)->whereIn('report_frequency', ['daily', 'weekly'])->whereNotNull('contact_email')
            ->each(function (Organizer $organizer) use (&$sent) {
                $weekly = $organizer->report_frequency === 'weekly';

                if ($weekly && ! now()->isMonday()) {
                    return;
                }
                if ($organizer->report_sent_at?->isToday()) {
                    return;
                }

                $since = $weekly ? now()->subDays(7) : now()->subDay();
                $rows = $this->rows($organizer, $since);
                if ($rows === []) {
                    return;
                }

                try {
                    Mail::to($organizer->contact_email)->send(new SalesReportMail($organizer, $rows, $weekly ? 'letzte 7 Tage' : 'letzte 24 Stunden'));
                    $organizer->forceFill(['report_sent_at' => now()])->save();
                    $sent++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        $this->info("$sent Report(s) gesendet.");

        return self::SUCCESS;
    }

    private function rows(Organizer $organizer, $since): array
    {
        return Event::where('organizer_id', $organizer->id)->where('status', 'published')
            ->where('starts_at', '>=', now()->subDay())->orderBy('starts_at')->get()
            ->map(function (Event $event) use ($since) {
                $paid = Ticket::where('event_id', $event->id)->where('status', 'valid')->whereHas('order', fn ($q) => $q->where('status', 'paid'));

                return [
                    'title' => $event->title,
                    'date' => $event->starts_at->translatedFormat('d.m.Y, H:i').' Uhr',
                    'new' => (clone $paid)->where('created_at', '>=', $since)->count(),
                    'sold' => (clone $paid)->count(),
                    'revenue' => Money::format((int) $event->orders()->where('status', 'paid')->sum('total_cents'), $event->currency),
                ];
            })->all();
    }
}
