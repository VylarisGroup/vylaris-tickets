<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    public function edit(Reseller $reseller)
    {
        return view('reseller.branding', compact('reseller'));
    }

    public function update(Request $request, Reseller $reseller): RedirectResponse
    {
        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:120'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'support_email' => ['nullable', 'email', 'max:190'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:1024'],
        ]);

        $reseller->fill([
            'brand_name' => $data['brand_name'] ?? null,
            'brand_color' => $data['brand_color'] ?? null,
            'support_email' => $data['support_email'] ?? null,
            'allow_registration' => $request->boolean('allow_registration'),
        ]);

        if ($request->hasFile('logo')) {
            if ($reseller->logo_path) {
                Storage::disk('public')->delete($reseller->logo_path);
            }
            $reseller->logo_path = $request->file('logo')->store('logos', 'public');
        } elseif ($request->boolean('remove_logo') && $reseller->logo_path) {
            Storage::disk('public')->delete($reseller->logo_path);
            $reseller->logo_path = null;
        }

        $reseller->save();

        return back()->with('status', 'Branding wurde gespeichert.');
    }
}
