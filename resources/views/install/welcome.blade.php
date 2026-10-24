@extends('install.layout')
@section('step')
<div>
    <h1 class="text-2xl font-bold">Willkommen!</h1>
    <p class="mt-1 text-sm text-slate-600">Ich richte mit dir Schritt für Schritt alles ein: Datenbank, Plattform, Administrator, E-Mail und auf Wunsch den ersten Veranstalter. Das dauert etwa fünf Minuten. Zuerst prüfe ich, ob dein Server bereit ist.</p>
</div>

@if ($needsToken)
    <form method="POST" action="{{ route('install.token') }}" class="space-y-3 rounded-lg bg-slate-50 p-4">@csrf
        <p class="text-sm text-slate-700">Zum Schutz vor Fremdzugriff verlangt diese Installation ein Setup-Token. Du findest es in der Datei <code>.env</code> unter <code>TICKETS_INSTALL_TOKEN</code>.</p>
        <x-field name="token" label="Setup-Token" type="password" required autocomplete="off" />
        <button class="btn btn-primary">Weiter</button>
    </form>
@else
    <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 text-sm">
        @foreach ($requirements as $r)
            <li class="flex items-start gap-3 px-4 py-2.5">
                <span class="mt-0.5 {{ $r['ok'] ? 'text-emerald-600' : ($r['hard'] ? 'text-red-600' : 'text-amber-600') }}" aria-hidden="true">{{ $r['ok'] ? '✓' : ($r['hard'] ? '✕' : '!') }}</span>
                <div>
                    <div>{{ $r['label'] }}</div>
                    @if ($r['hint'])<div class="text-xs {{ $r['hard'] ? 'text-red-700' : 'text-amber-700' }}">{{ $r['hint'] }}</div>@endif
                </div>
            </li>
        @endforeach
    </ul>

    @if ($ok)
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-emerald-700">Dein Server ist bereit.</p>
            <a href="{{ route('install.database') }}" class="btn btn-primary">Los geht's</a>
        </div>
    @else
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-red-700">Bitte behebe die mit ✕ markierten Punkte und lade die Seite neu.</p>
            <a href="{{ route('install.welcome') }}" class="btn btn-ghost">Erneut prüfen</a>
        </div>
    @endif
@endif
@endsection
