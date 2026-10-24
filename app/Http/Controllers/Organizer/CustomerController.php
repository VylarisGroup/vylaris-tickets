<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Kundenverwaltung: alle Käufer mit bezahlten Bestellungen, zusammengefasst nach E-Mail-Adresse. */
class CustomerController extends Controller
{
    public function index(Request $request, Organizer $organizer)
    {
        return view('organizer.customers.index', [
            'organizer' => $organizer,
            'customers' => $this->query($request, $organizer)->paginate(30)->withQueryString(),
            'q' => trim((string) $request->query('q')),
        ]);
    }

    public function export(Request $request, Organizer $organizer): StreamedResponse
    {
        $rows = $this->query($request, $organizer)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'E-Mail', 'Bestellungen', 'Tickets', 'Umsatz', 'Erste Bestellung', 'Letzte Bestellung'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [$r->name, $r->email, $r->orders_count, $r->tickets_count, number_format($r->revenue_cents / 100, 2, '.', ''), $r->first_at, $r->last_at], ';');
            }
            fclose($out);
        }, 'kunden-'.$organizer->slug.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $request, Organizer $organizer)
    {
        $q = trim((string) $request->query('q'));

        // Tickets je Bestellung vorab zusammenzählen (MySQL ONLY_FULL_GROUP_BY: keine Unterabfrage auf nicht gruppierte Spalten).
        $perOrder = DB::table('tickets')->where('status', 'valid')->selectRaw('order_id, COUNT(*) as ticket_count')->groupBy('order_id');

        return Order::query()->where('orders.organizer_id', $organizer->id)->leftJoinSub($perOrder, 'tc', 'tc.order_id', '=', 'orders.id')->where('orders.status', 'paid')->whereNotNull('orders.customer_email')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('orders.customer_name', 'like', $like)->orWhere('orders.customer_email', 'like', $like));
            })
            ->select([
                DB::raw('LOWER(orders.customer_email) as email'),
                DB::raw('MAX(orders.customer_name) as name'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(tc.ticket_count), 0) as tickets_count'),
                DB::raw('SUM(orders.total_cents) as revenue_cents'),
                DB::raw('MIN(orders.paid_at) as first_at'),
                DB::raw('MAX(orders.paid_at) as last_at'),
            ])
            ->groupBy(DB::raw('LOWER(orders.customer_email)'), 'orders.organizer_id')
            ->orderByDesc('last_at');
    }
}
