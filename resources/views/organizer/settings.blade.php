@extends('layouts.app')
@section('title', 'Einstellungen')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">Einstellungen</h1>
<form method="POST" action="{{ route('organizer.settings.update', $organizer) }}" enctype="multipart/form-data" class="max-w-2xl space-y-6">@csrf @method('PUT')
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

    <div class="card space-y-4">
        <h2 class="font-semibold">Rechtliches</h2>
        <p class="text-sm text-slate-500">Sind Links hinterlegt, müssen Käufer beim Bestellen zustimmen.</p>
        <x-field name="terms_url" label="Link zu deinen AGB" type="url" :value="$organizer->terms_url" placeholder="https://" />
        <x-field name="privacy_url" label="Link zur Datenschutzerklärung" type="url" :value="$organizer->privacy_url" placeholder="https://" />
    </div>

    <div class="card space-y-4">
        <h2 class="font-semibold">Ticketdesign</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="ticket_color">Ticketfarbe</label>
                <div class="flex items-center gap-2">
                    <input type="color" id="ticket_color" name="ticket_color" value="{{ old('ticket_color', $organizer->ticketColor()) }}" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-200 bg-transparent p-1">
                    <span class="text-xs text-slate-500">Kopfband und Akzente auf dem Ticket</span>
                </div>
                @error('ticket_color')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="logo">Logo auf dem Ticket</label>
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" class="input">
                @error('logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @if ($organizer->logoUrl())
                    <div class="mt-2 flex items-center gap-3"><img src="{{ $organizer->logoUrl() }}" alt="" class="h-10 rounded bg-white p-1"><label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300"> Logo entfernen</label></div>
                @endif
            </div>
        </div>
        <div>
            <label class="label" for="ticket_note">Hinweistext auf dem Ticket</label>
            <textarea id="ticket_note" name="ticket_note" rows="2" maxlength="500" class="input" placeholder="z. B. Türöffnung 1 h vor Beginn. Keine Glasflaschen.">{{ old('ticket_note', $organizer->ticket_note) }}</textarea>
        </div>
    </div>

    <div class="card space-y-4">
        <h2 class="font-semibold">Benachrichtigungen</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="reminder_hours" label="Erinnerung an Käufer (Stunden vor Beginn)" type="number" min="1" max="168" :value="$organizer->reminder_hours" hint="Leer = keine Erinnerung." />
            <div>
                <label class="label" for="report_frequency">Verkaufsreport per E-Mail</label>
                <select id="report_frequency" name="report_frequency" class="input">
                    @foreach (['none' => 'Kein Report', 'daily' => 'Täglich', 'weekly' => 'Wöchentlich (montags)'] as $k => $l)<option value="{{ $k }}" @selected(old('report_frequency', $organizer->report_frequency) === $k)>{{ $l }}</option>@endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Geht an die Kontakt-E-Mail.</p>
            </div>
        </div>
    </div>
    <button class="btn btn-primary">Speichern</button>
</form>
@endsection
