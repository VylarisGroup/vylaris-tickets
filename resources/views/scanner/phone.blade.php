@extends('layouts.base')
@section('title', 'Scanner '.$event->title)
@section('body')
<style>html, body { overscroll-behavior-y: contain; }</style>
<main x-data="scanner(@js($config))" class="mx-auto flex min-h-dvh max-w-xl flex-col gap-3 px-4 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-[calc(1rem+env(safe-area-inset-top))]">
    <header class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="text-xs uppercase tracking-wider text-slate-500">Kamera-Scanner</div>
            <h1 class="truncate text-lg font-extrabold tracking-tight">{{ $event->title }}</h1>
        </div>
        <div class="shrink-0 text-right"><div class="text-2xl font-extrabold"><span x-text="stats.checked_in"></span><span class="text-sm font-normal text-slate-500"> / <span x-text="stats.total"></span></span></div></div>
    </header>

    <div x-show="result" x-cloak class="rounded-2xl p-5 text-center shadow" :class="panelClass" role="status" aria-live="assertive">
        <div class="text-3xl font-extrabold" x-text="result?.title"></div>
        <template x-if="result?.ticket">
            <div class="mt-2">
                <div class="text-xl" x-text="result.ticket.name"></div>
                <div class="text-sm opacity-90"><span x-text="result.ticket.type"></span><span x-show="result.ticket.seat"> · <span x-text="result.ticket.seat"></span></span></div>
            </div>
        </template>
        <div class="mt-1 text-sm opacity-90" x-text="result?.detail"></div>
    </div>

    <div class="card flex-1 space-y-3">
        <button type="button" class="btn btn-primary w-full !py-4 text-base" @click="toggleCamera()" x-text="running ? 'Kamera stoppen' : 'Kamera starten'"></button>
        <p x-show="cameraError" x-cloak class="text-sm text-red-600" x-text="cameraError"></p>
        <div x-show="running" x-cloak class="overflow-hidden rounded-xl bg-black"><video x-ref="video" playsinline muted class="w-full"></video></div>
        <canvas x-ref="canvas" class="hidden"></canvas>
        <p x-show="!running" class="text-center text-sm text-slate-500">Starte die Kamera und halte sie auf den QR-Code des Tickets. Das Ergebnis erscheint hier und gross auf dem PC.</p>
    </div>

    <form class="card flex gap-2" @submit.prevent="submitManual()">
        <input x-model="manual" class="input flex-1 font-mono uppercase" placeholder="Ticketcode eingeben" autocomplete="off" autocapitalize="characters">
        <button class="btn btn-ghost">Prüfen</button>
    </form>
</main>
@endsection
