<!DOCTYPE html>
<html lang="de"><body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<div style="max-width:560px;margin:0 auto;padding:24px">
    <div style="background:#fff;border-radius:12px;padding:28px;border:1px solid #e2e8f0">
        @yield('content')
    </div>
    <p style="font-size:12px;color:#64748b;text-align:center;margin-top:16px">{{ $footer ?? config('tickets.name') }}</p>
</div>
</body></html>
