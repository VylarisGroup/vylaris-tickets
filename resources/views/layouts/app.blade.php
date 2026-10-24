@extends('layouts.base')
@section('body')
<header class="app-header border-b border-slate-200/70" x-data="{ open: false }">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-bold tracking-tight">
            @if ($brand['logo'])
                <img src="{{ Storage::disk('public')->url($brand['logo']) }}" alt="" class="h-8 w-auto">
            @else
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-brand text-sm font-bold text-white shadow-lg shadow-brand/30">T</span>
            @endif
            <span>{{ $brand['name'] }}</span>
        </a>
        <nav class="flex items-center gap-1 text-sm sm:gap-2">
            @can('platform-admin')
                <a href="{{ route('admin.dashboard') }}" class="nav-link">Administration</a>
            @endcan
            <span class="chip hidden sm:inline-flex">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" data-logout>@csrf
                <button class="nav-link">Abmelden</button>
            </form>
            <x-theme-toggle />
        </nav>
    </div>
    @hasSection('subnav')
        <div class="border-t border-slate-200/60">
            <div class="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-3 py-1.5 text-sm [scrollbar-width:none]">@yield('subnav')</div>
        </div>
    @endif
</header>
<div x-data="installHint" x-cloak x-show="visible" class="border-b border-brand/20 bg-brand/5 px-4 py-2 text-sm">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3">
        <div>
            <template x-if="canPrompt"><span>Als App installieren für schnelleren Zugriff auf Kasse und Einlass.</span></template>
            <template x-if="!canPrompt && ios"><span>Als App speichern: Teilen-Symbol <span aria-hidden="true">⎙</span> antippen, dann «Zum Home-Bildschirm».</span></template>
        </div>
        <div class="flex shrink-0 gap-2">
            <button type="button" x-show="canPrompt" class="btn btn-primary !px-3 !py-1" @click="install()">Installieren</button>
            <button type="button" class="btn btn-ghost !px-3 !py-1" @click="dismiss()">Später</button>
        </div>
    </div>
</div>
<main class="rise mx-auto max-w-6xl px-4 py-8 sm:py-10">
    <x-flash />
    @yield('content')
</main>
@endsection
