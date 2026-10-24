@extends('layouts.app')
@section('title', 'Einlass-Anzeige '.$event->title)
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<a href="{{ route('organizer.scan.index', $organizer) }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand"><x-icon name="arrow-left" :size="14" />Einlass</a>
<div class="mb-5 mt-1 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight">{{ $event->title }}</h1>
        <p class="text-sm text-slate-500">Einlass-Anzeige · Smartphone als Kamera-Scanner</p>
    </div>
    <a href="{{ route('organizer.scan.show', [$organizer, $event]) }}" class="btn btn-ghost">Scanner im Browser</a>
</div>

<div x-data="scanDisplay(@js($config))" class="grid gap-5 lg:grid-cols-[22rem_1fr]">
    {{-- Kopplung --}}
    <div class="space-y-4">
        <div class="card">
            @if ($pairing)
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">Smartphone koppeln</h2>
                    <span class="chip" :class="connected ? '!border-emerald-500/40 !text-emerald-500' : ''"><span class="h-2 w-2 rounded-full" :class="connected ? 'bg-[#059669]' : 'bg-slate-400'"></span><span x-text="connected ? 'Verbunden' : 'Wartet'"></span></span>
                </div>
                <div class="mx-auto w-fit rounded-2xl bg-white p-3"><img src="{{ $qr }}" alt="QR-Code zur Kopplung" class="h-52 w-52"></div>
                <p class="mt-3 text-center text-sm text-slate-500">QR-Code mit der Handy-Kamera scannen, oder auf dem Handy <span class="font-semibold text-slate-700">{{ parse_url(route('scanner.entry'), PHP_URL_HOST) }}/scanner</span> öffnen und den Code eingeben:</p>
                <div class="mt-2 text-center font-mono text-3xl font-bold tracking-[0.3em]">{{ $pairing->code }}</div>
                <p class="mt-2 text-center text-xs text-slate-500">Gültig bis {{ $pairing->expires_at->format('H:i') }} Uhr</p>
                <div class="mt-4 flex gap-2">
                    <form method="POST" action="{{ route('organizer.scan.display.pair', [$organizer, $event]) }}" class="flex-1">@csrf<button class="btn btn-ghost w-full">Neuer Code</button></form>
                    <form method="POST" action="{{ route('organizer.scan.display.revoke', [$organizer, $event, $pairing]) }}" class="flex-1" onsubmit="return confirm('Smartphone trennen?')">@csrf @method('DELETE')<button class="btn btn-ghost w-full !text-red-500">Trennen</button></form>
                </div>
            @else
                <div class="py-4 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand/10 text-brand"><x-icon name="scan" :size="24" /></div>
                    <h2 class="font-semibold">Smartphone als Scanner</h2>
                    <p class="mx-auto mt-1 max-w-xs text-sm text-slate-500">Das Handy dient nur als Kamera. Die Ergebnisse siehst du gross auf diesem Bildschirm.</p>
                    <form method="POST" action="{{ route('organizer.scan.display.pair', [$organizer, $event]) }}" class="mt-4">@csrf<button class="btn btn-primary">Kopplung starten</button></form>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="mb-2 flex items-end justify-between">
                <div class="text-sm text-slate-500">Eingelassen</div>
                <div class="text-3xl font-extrabold"><span x-text="stats.checked_in"></span> <span class="text-base font-normal text-slate-500">/ <span x-text="stats.total"></span></span></div>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-brand transition-all" :style="`width:${percent}%`"></div></div>
        </div>

        <div class="flex gap-2">
            <button type="button" class="btn btn-ghost flex-1" @click="toggleSound()" x-text="sound ? 'Ton an' : 'Ton aus'"></button>
            <button type="button" class="btn btn-ghost flex-1" @click="fullscreen()" x-text="full ? 'Vollbild beenden' : 'Vollbild'"></button>
        </div>
    </div>

    {{-- Anzeige --}}
    <div class="space-y-4">
        <div x-show="failures > 3" x-cloak class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">Keine Verbindung zum Server. Die Anzeige aktualisiert sich, sobald du wieder online bist.</div>
        <div x-show="!active" x-cloak class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">Diese Kopplung wurde beendet oder ist abgelaufen. Starte eine neue.</div>

        <div class="flex min-h-[18rem] flex-col items-center justify-center rounded-3xl p-8 text-center shadow-lg transition-colors duration-200"
             :class="current ? panelClass : 'card'" role="status" aria-live="assertive">
            <template x-if="!current">
                <div class="text-slate-500">
                    <x-icon name="scan" :size="56" class="mx-auto mb-3 opacity-40" />
                    <div class="text-xl font-semibold">Bereit zum Scannen</div>
                    <div class="mt-1 text-sm">Neue Scans erscheinen hier sofort.</div>
                </div>
            </template>
            <template x-if="current">
                <div>
                    <div class="text-5xl font-extrabold tracking-tight" x-text="current.title"></div>
                    <div class="mt-4 text-3xl font-semibold" x-text="current.holder"></div>
                    <div class="mt-1 text-xl opacity-90"><span x-text="current.type"></span><span x-show="current.seat"> · <span x-text="current.seat"></span></span></div>
                    <div class="mt-3 text-lg opacity-90" x-text="current.detail"></div>
                </div>
            </template>
        </div>

        <div class="card p-0">
            <div class="border-b border-slate-200/70 px-5 py-3 font-semibold">Letzte Scans</div>
            <ul class="divide-y divide-slate-100 text-sm">
                <template x-for="l in log" :key="l.id">
                    <li class="flex items-center gap-3 px-5 py-2.5">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="dot(l.status)"></span>
                        <div class="min-w-0 flex-1"><span class="font-medium" x-text="l.holder || l.title"></span> <span class="text-slate-500" x-show="l.type">· <span x-text="l.type"></span></span><div class="truncate text-xs text-slate-500" x-show="l.holder" x-text="l.title"></div></div>
                        <span class="shrink-0 text-xs text-slate-500" x-text="l.time"></span>
                    </li>
                </template>
                <li x-show="!log.length" class="px-5 py-4 text-slate-500">Noch keine Scans.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
