<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Moderation gemeldeter Events. */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');

        $reports = Report::with('event.organizer')->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest('id')->paginate(25)->withQueryString();

        return view('admin.reports', ['reports' => $reports, 'status' => $status, 'openCount' => Report::where('status', 'open')->count()]);
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:dismiss,resolve,block,unblock'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $event = $report->event;

        match ($data['action']) {
            'dismiss' => $report->update(['status' => 'dismissed']),
            'resolve' => $report->update(['status' => 'resolved']),
            'block' => $this->block($report, $data['reason'] ?? null),
            'unblock' => $event->update(['is_blocked' => false, 'blocked_reason' => null]),
        };

        if (in_array($data['action'], ['dismiss', 'resolve', 'block'], true)) {
            $report->update(['handled_by' => $request->user()->id, 'handled_at' => now()]);
        }

        return back()->with('status', 'Meldung aktualisiert.');
    }

    private function block(Report $report, ?string $reason): void
    {
        // Alle offenen Meldungen zum selben Event sind mit der Sperre erledigt.
        $report->event->update(['is_blocked' => true, 'blocked_reason' => $reason ?: $report->reasonLabel()]);
        Report::where('event_id', $report->event_id)->where('status', 'open')->update(['status' => 'resolved']);
        $report->update(['status' => 'resolved']);
    }
}
