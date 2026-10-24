@extends('layouts.app')
@section('title', 'Updates')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">Updates</h1>
@error('update')<div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

<div class="card max-w-2xl space-y-4">
    <div class="flex items-center justify-between">
        <div><div class="text-sm text-slate-500">Installierte Version</div><div class="text-2xl font-bold">{{ $current }}</div></div>
        <a href="{{ route('admin.update', ['refresh' => 1]) }}" class="btn btn-ghost">Erneut prüfen</a>
    </div>

    @if ($error)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $error }}</div>
    @elseif ($latest && $latest['newer'])
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Version <strong>{{ $latest['version'] }}</strong> ist verfügbar.</div>
        @if ($latest['notes'])<div class="whitespace-pre-line rounded-lg bg-slate-50 p-4 text-sm text-slate-700">{{ $latest['notes'] }}</div>@endif
        @if ($latest['zip_url'])
            <form method="POST" action="{{ route('admin.update.install') }}" onsubmit="return confirm('Update jetzt installieren? Die Seite ist kurz im Wartungsmodus.')" class="space-y-2">@csrf
                <input type="hidden" name="version" value="{{ $latest['version'] }}">
                <button class="btn btn-primary">Jetzt auf {{ $latest['version'] }} aktualisieren</button>
                <p class="text-xs text-slate-500">Vorher wird automatisch eine Sicherung erstellt. Bei einem Fehler wird zurückgerollt. Bitte das Fenster während der Installation nicht schliessen.</p>
            </form>
        @endif
    @elseif ($latest)
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">Du verwendest die neueste Version.</div>
    @endif

    <div class="border-t border-slate-100 pt-4 text-xs text-slate-500">
        <div>Composer auf dem Server: <strong>{{ $deps['composer'] ?? 'nicht gefunden' }}</strong> · PHP (Konsole): <strong>{{ $deps['php'] ?? 'nicht gefunden' }}</strong></div>
        <div class="mt-1">Scheitert das Update hier, kannst du es auf dem Server in der Konsole neu anstossen: <code class="rounded bg-slate-100 px-1.5 py-0.5">php artisan tickets:update</code></div>
    </div>

    @if ($status)
        <div class="border-t border-slate-100 pt-4 text-sm text-slate-600">Letzter Vorgang ({{ \Illuminate\Support\Carbon::parse($status['at'])->format('d.m.Y H:i') }}): {{ $status['message'] }}</div>
    @endif
</div>
<p class="mt-4 max-w-2xl text-xs text-slate-500">Updates werden ausschliesslich aus dem offiziellen Repository <code>VylarisGroup/vylaris-tickets</code> bezogen über eine verschlüsselte HTTPS-Verbindung geladen.</p>
@endsection
