<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Mail\EventMessageMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Nachricht an alle Personen mit bezahlter Bestellung (z. B. Programmänderung, Absage). */
class MessageController extends Controller
{
    public function create(Organizer $organizer, Event $event)
    {
        return view('organizer.messages.create', ['organizer' => $organizer, 'event' => $event, 'recipients' => $this->recipients($event)->count()]);
    }

    public function store(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'test' => ['nullable', 'boolean'],
        ]);

        // Testversand nur an die eigene Adresse
        if ($request->boolean('test')) {
            Mail::to($request->user()->email)->send(new EventMessageMail($event->load('organizer'), '[Test] '.$data['subject'], $data['body'], $organizer->contact_email));

            return back()->withInput()->with('status', 'Testnachricht wurde an '.$request->user()->email.' gesendet.');
        }

        $emails = $this->recipients($event);
        if ($emails->isEmpty()) {
            return back()->withInput()->withErrors(['subject' => 'Es gibt noch keine Empfänger mit bezahlter Bestellung.']);
        }

        $event->load('organizer');
        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new EventMessageMail($event, $data['subject'], $data['body'], $organizer->contact_email));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('organizer.events.show', [$organizer, $event])->with('status', 'Nachricht wurde an '.$emails->count().' Empfänger gesendet.');
    }

    private function recipients(Event $event)
    {
        return Order::where('event_id', $event->id)->where('status', 'paid')->whereNotNull('customer_email')
            ->pluck('customer_email')->map(fn ($e) => mb_strtolower(trim($e)))->filter()->unique()->values();
    }
}
