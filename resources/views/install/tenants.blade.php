@extends('install.layout')
@section('step')
<div>
    <h1 class="text-2xl font-bold">Erster Veranstalter</h1>
    <p class="mt-1 text-sm text-slate-600">Wenn du die Plattform selbst für Events nutzt, lege jetzt deinen Veranstalter an. Du wirst dessen Inhaber. Wer die Plattform nur anbietet, überspringt den Schritt.</p>
</div>

@if ($exists)
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Es gibt bereits einen Veranstalter.</p>
    <div class="flex justify-end"><a href="{{ route('install.done') }}" class="btn btn-primary">Weiter</a></div>
@else
<form method="POST" action="{{ route('install.tenants.save') }}" class="space-y-4" x-data="{ reseller: {{ old('use_reseller') ? 'true' : 'false' }}, vat: {{ old('vat_enabled') ? 'true' : 'false' }} }">@csrf
    <x-field name="organizer_name" label="Name des Veranstalters" required placeholder="z. B. Verein Musterstadt" />
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label" for="currency">Währung</label>
            <select id="currency" name="currency" class="input"><option>CHF</option><option>EUR</option></select>
        </div>
        <x-field name="iban" label="IBAN für Überweisungen (optional)" placeholder="CH93 0076 2011 6238 5295 7" />
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="vat_enabled" value="1" x-model="vat" class="rounded border-slate-300"> Preise enthalten MWST (wird auf Tickets ausgewiesen)</label>
    <div x-show="vat" x-cloak><x-field name="vat_rate" label="MWST-Satz in %" value="8.1" inputmode="decimal" class="sm:w-40" /></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="demo_event" value="1" class="rounded border-slate-300" checked> Beispiel-Event als Entwurf anlegen (zum Ausprobieren)</label>

    <div class="border-t border-slate-100 pt-4">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="use_reseller" value="1" x-model="reseller" class="rounded border-slate-300"> Den Veranstalter einem eigenen Reseller zuordnen (Whitelabel mit eigener Subdomain)</label>
        <div x-show="reseller" x-cloak class="mt-3"><x-field name="reseller_name" label="Name des Resellers" placeholder="z. B. Eventhaus Bern" /></div>
    </div>

    <div class="flex flex-wrap justify-between gap-3">
        <button name="action" value="skip" class="btn btn-ghost" formnovalidate>Überspringen</button>
        <button name="action" value="save" class="btn btn-primary">Anlegen und weiter</button>
    </div>
</form>
@endif
@endsection
