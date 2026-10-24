@php($mb = \App\Support\MailBrand::for($reseller ?? null))
<!DOCTYPE html>
<html lang="de"><body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<div style="max-width:560px;margin:0 auto;padding:24px">
    <div style="border-radius:12px 12px 0 0;padding:16px 28px;background:{{ $mb['color'] }};color:#fff">
        @if ($mb['logo'])<img src="{{ $mb['logo'] }}" alt="{{ $mb['name'] }}" style="max-height:36px;max-width:200px;vertical-align:middle">@else<span style="font-size:18px;font-weight:bold">{{ $mb['name'] }}</span>@endif
    </div>
    <div style="background:#fff;border-radius:0 0 12px 12px;padding:28px;border:1px solid #e2e8f0;border-top:0">
        @yield('content')
    </div>
    <p style="font-size:12px;color:#64748b;text-align:center;margin-top:16px">{{ $footer ?? $mb['name'] }}@if (($footer ?? null) && $footer !== $mb['name']) · {{ $mb['name'] }}@endif
        @if ($mb['support_email'])<br>Fragen? <a href="mailto:{{ $mb['support_email'] }}" style="color:#64748b">{{ $mb['support_email'] }}</a>@endif</p>
</div>
</body></html>
