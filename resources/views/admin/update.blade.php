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
        @if ($latest['asset_url'] && $latest['sha256'])
            <form method="POST" action="{{ route('admin.update.install') }}" onsubmit="return confirm('Update jetzt installieren? Die Seite ist kurz im Wartungsmodus.')" class="space-y-2">@csrf
                <input type="hidden" name="version" value="{{ $latest['version'] }}">
                <button class="btn btn-primary">Jetzt auf {{ $latest['version'] }} aktualisieren</button>
                <p class="text-xs text-slate-500">Vorher wird automatisch eine Sicherung erstellt. Bei einem Fehler wird zurückgerollt. Bitte das Fenster während der Installation nicht schliessen.</p>
            </form>
        @else
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Für dieses Release steht noch kein geprüftes Installationspaket bereit.</div>
        @endif
    @elseif ($latest)
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">Du verwendest die neueste Version.</div>
    @endif

    @if ($status)
        <div class="border-t border-slate-100 pt-4 text-sm text-slate-600">Letzter Vorgang ({{ \Illuminate\Support\Carbon::parse($status['at'])->format('d.m.Y H:i') }}): {{ $status['message'] }}</div>
    @endif
</div>
<p class="mt-4 max-w-2xl text-xs text-slate-500">Updates werden ausschliesslich aus dem offiziellen Repository <code>VylarisGroup/vylaris-tickets</code> bezogen und per Prüfsumme verifiziert.</p>
@endsection
