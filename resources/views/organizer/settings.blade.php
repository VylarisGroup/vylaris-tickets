@extends('layouts.app')
@section('title', 'Einstellungen')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">Einstellungen</h1>
<form method="POST" action="{{ route('organizer.settings.update', $organizer) }}" class="max-w-2xl space-y-6">@csrf @method('PUT')
    <div class="card space-y-4">
        <h2 class="font-semibold">Stammdaten</h2>
        <x-field name="name" label="Name" :value="$organizer->name" required />
        <x-field name="contact_email" label="Kontakt-E-Mail" type="email" :value="$organizer->contact_email" />
        <x-field name="street" label="Strasse" :value="$organizer->street" />
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field name="zip" label="PLZ" :value="$organizer->zip" />
            <x-field name="city" label="Ort" :value="$organizer->city" class="sm:col-span-2" />
        </div>
        <x-field name="country" label="Land (ISO, z. B. CH)" :value="$organizer->country" maxlength="2" required />
    </div>

    <div class="card space-y-4">
        <h2 class="font-semibold">Währung und MWST</h2>
        <div>
            <label class="label" for="default_currency">Standardwährung</label>
            <select id="default_currency" name="default_currency" class="input">
                @foreach (config('tickets.currencies') as $c)<option @selected(old('default_currency', $organizer->default_currency) === $c)>{{ $c }}</option>@endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="vat_enabled" value="1" @checked(old('vat_enabled', $organizer->vat_enabled)) class="rounded border-slate-300"> MWST auf Tickets und Rechnungen ausweisen</label>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="vat_number" label="MWST-Nummer" :value="$organizer->vat_number" placeholder="CHE-123.456.789 MWST" />
            <x-field name="vat_rate" label="MWST-Satz in %" type="number" step="0.01" :value="$organizer->vat_rate" />
        </div>
    </div>

    <div class="card space-y-4">
        <h2 class="font-semibold">Zahlungen</h2>
        <p class="text-sm text-slate-500">Das Geld der Ticketkäufe geht direkt auf dein Konto beim Zahlungsanbieter. Schlüssel werden verschlüsselt gespeichert. Leere Felder behalten den bisherigen Wert.</p>
        <x-field name="iban" label="IBAN (für Banküberweisung)" :value="$organizer->iban" />
        @foreach (['mollie_api_key' => 'Mollie API-Schlüssel', 'stripe_secret_key' => 'Stripe Secret Key', 'stripe_publishable_key' => 'Stripe Publishable Key'] as $key => $label)
            <div>
                <x-field :name="$key" :label="$label" type="password" autocomplete="off" :hint="! empty($payment[$key]) ? 'Gespeichert.' : 'Nicht hinterlegt.'" />
                @if (! empty($payment[$key]))
                    <label class="mt-1 flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="clear_{{ $key }}" value="1" class="rounded border-slate-300"> Schlüssel entfernen</label>
                @endif
            </div>
        @endforeach
    </div>
    <button class="btn btn-primary">Speichern</button>
</form>
@endsection
