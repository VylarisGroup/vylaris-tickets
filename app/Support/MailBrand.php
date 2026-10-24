<?php

namespace App\Support;

use App\Models\Reseller;
use Illuminate\Support\Facades\Storage;

/** Darstellung der Absender-Marke in E-Mails: Plattform oder Reseller (Whitelabel). */
class MailBrand
{
    /** @return array{name:string,color:string,logo:?string,support_email:?string} */
    public static function for(?Reseller $reseller): array
    {
        $logo = $reseller?->logo_path ? Storage::disk('public')->url($reseller->logo_path) : null;
        if ($logo && ! preg_match('#^https?://#', $logo)) {
            $logo = url($logo);
        }

        return [
            'name' => $reseller?->displayName() ?? config('tickets.name'),
            'color' => $reseller?->color() ?? config('tickets.brand_color'),
            'logo' => $logo,
            'support_email' => $reseller?->support_email,
        ];
    }
}
