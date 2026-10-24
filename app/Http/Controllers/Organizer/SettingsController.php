<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'terms_url' => ['nullable', 'url:https,http', 'max:500'],
            'privacy_url' => ['nullable', 'url:https,http', 'max:500'],
            'ticket_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'ticket_note' => ['nullable', 'string', 'max:500'],
            'reminder_hours' => ['nullable', 'integer', 'between:1,168'],
            'report_frequency' => ['nullable', Rule::in(['none', 'daily', 'weekly'])],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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

        // Unverändert übernommene Standardfarbe nicht festschreiben (Reseller-Farbe bleibt wirksam).
        if (! $organizer->ticket_color && strcasecmp((string) ($data['ticket_color'] ?? ''), $organizer->ticketColor()) === 0) {
            $data['ticket_color'] = null;
        }

        $logo = $organizer->logo_path;
        if ($request->boolean('remove_logo') && $logo) {
            Storage::disk('public')->delete($logo);
            $logo = null;
        }
        if ($request->hasFile('logo')) {
            if ($logo) {
                Storage::disk('public')->delete($logo);
            }
            $logo = $request->file('logo')->store('organizers/'.$organizer->id, 'public');
        }

        $organizer->update([
            ...collect($data)->only(['name', 'contact_email', 'street', 'zip', 'city', 'vat_number', 'default_currency', 'iban', 'terms_url', 'privacy_url', 'ticket_color', 'ticket_note', 'reminder_hours'])->all(),
            'report_frequency' => $data['report_frequency'] ?? $organizer->report_frequency,
            'logo_path' => $logo,
            'country' => strtoupper($data['country']),
            'vat_enabled' => $request->boolean('vat_enabled'),
            'vat_rate' => $data['vat_rate'] ?? $organizer->vat_rate,
            'payment_settings' => $payment ?: null,
        ]);

        return back()->with('status', 'Einstellungen wurden gespeichert.');
    }
}
