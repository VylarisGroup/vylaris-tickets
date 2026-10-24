<?php

namespace App\Http\Controllers\Organizer;

use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organizer;
use App\Services\OrderService;
use App\Services\SeatMapData;
use App\Services\TicketPdf;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Abendkasse: Verkauf vor Ort, Kassenschichten und Kassenabschluss. */
class DoorController extends Controller
{
    public function index(Organizer $organizer)
    {
        $events = $organizer->events()->where('status', 'published')->where('is_blocked', false)
            ->where('door_sales_enabled', true)->where('type', '!=', 'digital')
            ->where('starts_at', '>=', now()->subDays(2))->orderBy('starts_at')->get();

        return view('organizer.door.index', compact('organizer', 'events'));
    }

    public function show(Request $request, Organizer $organizer, Event $event, SeatMapData $maps)
    {
        $session = $this->openSession($request, $event);

        $view = ['organizer' => $organizer, 'event' => $event, 'session' => $session];

        if (! $session) {
            return view('organizer.door.open', $view + ['previous' => $this->sessionsFor($event)->limit(5)->get()]);
        }

        $config = [
            'types' => $maps->types($event, true)->keyBy('id'),
            'currency' => $event->currency,
            'seating' => $event->seating_mode->isSeated() ? $maps->for($event) : null,
            'maxSeats' => 50,
            'old' => ['items' => old('items', []), 'seats' => array_values(array_map('strval', old('seats', [])))],
        ];

        return view('organizer.door.sell', $view + [
            'types' => $config['types'],
            'config' => $config,
            'recent' => $session->orders()->latest('id')->limit(8)->get(),
            'stats' => $this->stats($session),
        ]);
    }

    public function open(Request $request, Organizer $organizer, Event $event): RedirectResponse
    {
        abort_if($this->openSession($request, $event), 409, 'Es ist bereits eine Kasse geöffnet.');

        $data = $request->validate(['opening_float' => ['nullable', 'string', 'max:15']]);
        $float = Money::toCents($data['opening_float'] ?? '0');

        if ($float === null || $float < 0) {
            return back()->withErrors(['opening_float' => 'Bitte einen gültigen Betrag eingeben.'])->withInput();
        }

        CashSession::create([
            'organizer_id' => $organizer->id, 'event_id' => $event->id, 'user_id' => $request->user()->id,
            'opening_float_cents' => $float, 'opened_at' => now(),
        ]);

        return redirect()->route('organizer.door.show', [$organizer, $event]);
    }

    public function sell(Request $request, Organizer $organizer, Event $event, OrderService $orders): RedirectResponse
    {
        $session = $this->openSession($request, $event);
        abort_unless($session, 409, 'Bitte zuerst die Kasse öffnen.');

        $data = $request->validate([
            'items' => ['nullable', 'array'], 'items.*' => ['nullable', 'integer', 'min:0', 'max:500'],
            'seats' => ['nullable', 'array'], 'seats.*' => ['regex:/^\d+(x\d{1,3})?$/'],
            'method' => ['required', 'in:cash,external'],
            'voucher' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $order = $orders->place(
                $event, $data['items'] ?? [], $data['seats'] ?? [],
                ['name' => $data['name'] ?? '', 'email' => $data['email'] ?? null],
                $data['voucher'] ?? null, $data['method'], 'door', $request->user(), $session,
            );
        } catch (CheckoutException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()])->withInput();
        }

        return redirect()->route('organizer.door.receipt', [$organizer, $event, $order]);
    }

    public function receipt(Organizer $organizer, Event $event, Order $order)
    {
        $this->guardOrder($event, $order);

        return view('organizer.door.receipt', ['organizer' => $organizer, 'event' => $event, 'order' => $order->load('tickets.ticketType', 'tickets.seat')]);
    }

    public function pdf(Organizer $organizer, Event $event, Order $order, TicketPdf $pdf)
    {
        $this->guardOrder($event, $order);
        abort_unless($order->isPaid(), 404);

        return response($pdf->render($order), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$order->number.'.pdf"']);
    }

    public function void(Request $request, Organizer $organizer, Event $event, Order $order, OrderService $orders): RedirectResponse
    {
        $this->guardOrder($event, $order);
        abort_unless($order->channel === 'door' && $order->cashSession?->isOpen(), 403, 'Nur Verkäufe der offenen Kasse können storniert werden.');

        $orders->cancel($order);

        return redirect()->route('organizer.door.show', [$organizer, $event])->with('status', 'Verkauf '.$order->number.' storniert.');
    }

    public function close(Request $request, Organizer $organizer, Event $event, CashSession $session): RedirectResponse
    {
        $this->guardSession($event, $session);
        abort_unless($session->isOpen(), 409);
        abort_unless($session->user_id === $request->user()->id || $request->user()->canManageOrganizer($organizer), 403);

        $data = $request->validate(['counted_cash' => ['required', 'string', 'max:15'], 'note' => ['nullable', 'string', 'max:255']]);
        $counted = Money::toCents($data['counted_cash']);

        if ($counted === null || $counted < 0) {
            return back()->withErrors(['counted_cash' => 'Bitte den gezählten Bargeldbetrag eingeben.'])->withInput();
        }

        $session->update(['closed_at' => now(), 'counted_cash_cents' => $counted, 'note' => $data['note'] ?? null]);

        return redirect()->route('organizer.door.report', [$organizer, $event, $session]);
    }

    public function report(Request $request, Organizer $organizer, Event $event, CashSession $session)
    {
        $this->guardSession($event, $session);
        abort_unless($session->user_id === $request->user()->id || $request->user()->canManageOrganizer($organizer), 403);

        return view('organizer.door.report', [
            'organizer' => $organizer, 'event' => $event, 'session' => $session->load('user'),
            'stats' => $this->stats($session),
            'orders' => $session->orders()->with('tickets')->orderBy('id')->get(),
        ]);
    }

    public function sessions(Organizer $organizer, Event $event)
    {
        return view('organizer.door.sessions', ['organizer' => $organizer, 'event' => $event, 'sessions' => $this->sessionsFor($event)->with('user')->get()]);
    }

    public function export(Request $request, Organizer $organizer, Event $event, CashSession $session): StreamedResponse
    {
        $this->guardSession($event, $session);
        abort_unless($session->user_id === $request->user()->id || $request->user()->canManageOrganizer($organizer), 403);

        return $this->csv('kasse-'.$session->id.'.csv', $session->orders()->with('tickets.ticketType')->orderBy('id')->get());
    }

    // ---------------------------------------------------------------------------------------------

    /** @return array{paid:int,tickets:int,by_method:array<string,array{count:int,total:int}>,expected_cash:int} */
    public function stats(CashSession $session): array
    {
        $paid = $session->orders()->where('status', 'paid')->withCount(['tickets as valid_tickets' => fn ($q) => $q->where('status', 'valid')])->get();

        $byMethod = [];
        foreach ($paid->groupBy('payment_method') as $method => $group) {
            $byMethod[$method] = ['count' => $group->count(), 'total' => (int) $group->sum('total_cents')];
        }

        return [
            'paid' => (int) $paid->sum('total_cents'),
            'tickets' => (int) $paid->sum('valid_tickets'),
            'by_method' => $byMethod,
            'expected_cash' => $session->expectedCashCents(),
        ];
    }

    public static function csv(string $filename, $orders): StreamedResponse
    {
        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM für Excel
            fputcsv($out, ['Bestellung', 'Datum', 'Status', 'Kanal', 'Zahlungsart', 'Name', 'E-Mail', 'Tickets', 'Zwischensumme', 'Rabatt', 'Gutschein', 'Total', 'davon MWST', 'Währung'], ';');
            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->number, $o->created_at->format('d.m.Y H:i'), $o->statusLabel(), $o->channel === 'door' ? 'Abendkasse' : 'Online',
                    Order::methodLabel($o->payment_method), $o->customer_name, $o->customer_email,
                    $o->tickets->where('status', 'valid')->count() ?: $o->tickets->count(),
                    Money::input($o->subtotal_cents), Money::input($o->discount_cents), $o->voucher_code,
                    Money::input($o->total_cents), Money::input($o->vat_cents), $o->currency,
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function openSession(Request $request, Event $event): ?CashSession
    {
        return CashSession::where('event_id', $event->id)->where('user_id', $request->user()->id)->whereNull('closed_at')->latest('id')->first();
    }

    private function sessionsFor(Event $event)
    {
        return CashSession::where('event_id', $event->id)->latest('id');
    }

    private function guardOrder(Event $event, Order $order): void
    {
        abort_unless($order->event_id === $event->id, 404);
    }

    private function guardSession(Event $event, CashSession $session): void
    {
        abort_unless($session->event_id === $event->id, 404);
    }
}
