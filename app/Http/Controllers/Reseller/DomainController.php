<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerDomain;
use App\Services\DomainGuide;
use App\Services\DomainVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Eigene Domains per CNAME registrieren. */
class DomainController extends Controller
{
    public function index(Reseller $reseller, DomainVerifier $verifier, DomainGuide $guide)
    {
        $domains = $reseller->domains()->orderBy('domain')->get();

        return view('reseller.domains', [
            'reseller' => $reseller,
            'domains' => $domains,
            'guide' => $guide,
            // Aktueller DNS-Stand und nötige Einträge nur für noch nicht bestätigte Domains.
            'pending' => $domains->reject->isVerified()->mapWithKeys(fn ($d) => [$d->id => ['records' => $guide->records($d->domain), 'status' => $guide->status($d->domain)]]),
            'ips' => $guide->targetIps(),
            'target' => $verifier->target(),
            'limit' => (int) config('tickets.max_domains_per_reseller'),
        ]);
    }

    public function store(Request $request, Reseller $reseller, DomainVerifier $verifier): RedirectResponse
    {
        $data = $request->validate(['domain' => ['required', 'string', 'max:255']]);

        try {
            $record = $verifier->add($reseller, $data['domain']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['domain' => $e->getMessage()]);
        }

        $verifier->verify($record);

        return redirect()->route('reseller.domains.index', $reseller)->with('status', $record->isVerified()
            ? 'Domain bestätigt und aktiv.'
            : 'Domain hinzugefügt. Lege jetzt den CNAME-Eintrag an und klicke danach auf «Prüfen».');
    }

    public function verify(Reseller $reseller, ResellerDomain $domain, DomainVerifier $verifier): RedirectResponse
    {
        $ok = $verifier->verify($domain);

        return back()->with($ok ? 'status' : 'warning', $ok ? 'Domain bestätigt und aktiv.' : 'Noch nicht bestätigt: '.$domain->last_error);
    }

    public function destroy(Reseller $reseller, ResellerDomain $domain, \App\Services\PleskSync $plesk): RedirectResponse
    {
        $plesk->forget($domain);
        $domain->delete();

        return back()->with('status', 'Domain entfernt.');
    }
}
