@extends('layouts.app')
@section('title', 'Einlass '.$event->title)
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
@php
    $config = [
        'stats' => $stats,
        'canUndo' => auth()->user()->canManageOrganizer($organizer),
        'urls' => [
            'check' => route('organizer.scan.check', [$organizer, $event]),
            'search' => route('organizer.scan.search', [$organizer, $event]),
            'admit' => route('organizer.scan.admit', [$organizer, $event, '__ID__']),
            'undo' => route('organizer.scan.undo', [$organizer, $event, '__ID__']),
        ],
    ];
@endphp
<style>html, body { overscroll-behavior-y: contain; }</style>
<a href="{{ route('organizer.scan.index', $organizer) }}" class="text-sm text-slate-500 hover:text-brand">← Einlass</a>
<div class="mb-4 mt-1 flex flex-wrap items-center justify-between gap-2">
    <h1 class="text-2xl font-bold">{{ $event->title }}</h1>
    <a href="{{ route('organizer.scan.display', [$organizer, $event]) }}" class="btn btn-ghost">Anzeige am PC, Handy als Scanner</a>
</div>

<div x-data="scanner(@js($config))" class="mx-auto max-w-xl space-y-4">
    <div class="card">
        <div class="mb-2 flex items-end justify-between">
            <div class="text-sm text-slate-500">Eingelassen</div>
            <div class="text-2xl font-bold"><span x-text="stats.checked_in"></span> <span class="text-base font-normal text-slate-500">/ <span x-text="stats.total"></span></span></div>
        </div>
        <div class="mb-2 text-xs text-slate-500">Aktuell im Haus: <span class="font-semibold text-slate-700" x-text="stats.inside"></span></div>
        <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-brand transition-all" :style="`width:${percent}%`"></div></div>
    </div>

    <div class="grid grid-cols-2 gap-1 rounded-2xl border border-slate-200 p-1" role="group" aria-label="Modus">
        <button type="button" class="rounded-xl px-3 py-2 text-sm font-semibold transition-colors" :class="mode === 'in' ? 'bg-brand text-white' : 'text-slate-500'" @click="mode = 'in'" :aria-pressed="mode === 'in'">Eingang</button>
        <button type="button" class="rounded-xl px-3 py-2 text-sm font-semibold transition-colors" :class="mode === 'out' ? 'bg-[#2563eb] text-white' : 'text-slate-500'" @click="mode = 'out'" :aria-pressed="mode === 'out'">Ausgang</button>
    </div>

    <div x-show="result" x-cloak class="rounded-2xl p-5 text-center shadow" :class="panelClass" role="status" aria-live="assertive">
        <div class="text-2xl font-bold" x-text="result?.title"></div>
        <template x-if="result?.ticket">
            <div class="mt-2">
                <div class="text-lg" x-text="result.ticket.name"></div>
                <div class="text-sm opacity-90"><span x-text="result.ticket.type"></span><span x-show="result.ticket.seat"> · <span x-text="result.ticket.seat"></span></span></div>
            </div>
        </template>
        <div class="mt-1 text-sm opacity-90" x-text="result?.detail"></div>
    </div>

    <div class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm">
        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#059669]"></span>
        <div><div class="font-medium">Handscanner bereit</div>
            <div class="text-slate-500">Ein per Kabel angeschlossener Scanner (Tastatur-Modus) funktioniert direkt: einfach den Strichcode oder QR-Code scannen, egal wo der Cursor steht. <span x-show="wedge" x-cloak>Zuletzt gelesen: <span class="font-mono" x-text="wedge?.last"></span></span></div></div>
    </div>

    <div class="card space-y-3">
        <button type="button" class="btn btn-primary w-full" @click="toggleCamera()" x-text="running ? 'Kamera stoppen' : 'Kamera starten'"></button>
        <p x-show="cameraError" x-cloak class="text-sm text-red-600" x-text="cameraError"></p>
        <div x-show="running" x-cloak class="overflow-hidden rounded-xl bg-black">
            <video x-ref="video" playsinline muted class="w-full"></video>
        </div>
        <canvas x-ref="canvas" class="hidden"></canvas>
    </div>

    <form class="card flex gap-2" @submit.prevent="submitManual()">
        <input x-model="manual" class="input flex-1 font-mono uppercase" placeholder="Ticketcode eingeben" autocomplete="off" autocapitalize="characters">
        <button class="btn btn-ghost">Prüfen</button>
    </form>

    <div class="card space-y-3">
        <h2 class="font-semibold">Suche nach Name oder Bestellung</h2>
        <input x-model="query" @input.debounce.300ms="search()" class="input" placeholder="Name, E-Mail oder Bestellnummer" autocomplete="off">
        <ul class="divide-y divide-slate-100 text-sm">
            <template x-for="t in found" :key="t.id">
                <li class="flex items-center justify-between gap-3 py-2">
                    <div>
                        <div class="font-medium" x-text="t.name"></div>
                        <div class="text-slate-500"><span x-text="t.type"></span><span x-show="t.seat"> · <span x-text="t.seat"></span></span> · <span x-text="t.order"></span></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <template x-if="t.state === 'valid'"><button type="button" class="btn btn-primary" @click="admit(t)">Einlassen</button></template>
                        <template x-if="t.state === 'used'"><span class="text-amber-700">Eingelöst <span x-text="t.checked_in_at"></span></span></template>
                        <template x-if="t.state === 'used' && canUndo"><button type="button" class="text-xs text-slate-500 underline" @click="undo(t)">zurücksetzen</button></template>
                        <template x-if="t.state === 'unpaid'"><span class="text-red-600">Nicht bezahlt</span></template>
                        <template x-if="t.state === 'cancelled'"><span class="text-red-600">Ungültig</span></template>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>
@endsection
