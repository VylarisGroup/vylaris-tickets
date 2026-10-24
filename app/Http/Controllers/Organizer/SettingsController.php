<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function edit(Organizer $organizer)
    {
        return view('organizer.settings', [
            'organizer' => $organizer,
            'payment' => $organizer->payment_settings ?? [],
        ]);
    }

    public function update(Request $request, Organizer $organizer): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'street' => ['nullable', 'string', 'max:150'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'vat_number' => ['nullable', 'string', 'max:40'],
            'vat_rate' => ['nullable', 'numeric', 'between:0,30'],
            'default_currency' => ['required', Rule::in(config('tickets.currencies'))],
            'iban' => ['nullable', 'string', 'max:40'],
            'mollie_api_key' => ['nullable', 'string', 'max:200'],
            'stripe_secret_key' => ['nullable', 'string', 'max:200'],
            'stripe_publishable_key' => ['nullable', 'string', 'max:200'],
        ]);

        $payment = $organizer->payment_settings ?? [];

        // Leere Felder behalten den gespeicherten Schlüssel; "Entfernen" löscht ihn.
        foreach (['mollie_api_key', 'stripe_secret_key', 'stripe_publishable_key'] as $key) {
            if (! empty($data[$key])) {
                $payment[$key] = $data[$key];
            } elseif ($request->boolean('clear_'.$key)) {
                unset($payment[$key]);
            }
        }

        $organizer->update([
            ...collect($data)->only(['name', 'contact_email', 'street', 'zip', 'city', 'vat_number', 'default_currency', 'iban'])->all(),
            'country' => strtoupper($data['country']),
            'vat_enabled' => $request->boolean('vat_enabled'),
            'vat_rate' => $data['vat_rate'] ?? $organizer->vat_rate,
            'payment_settings' => $payment ?: null,
        ]);

        return back()->with('status', 'Einstellungen wurden gespeichert.');
    }
}
