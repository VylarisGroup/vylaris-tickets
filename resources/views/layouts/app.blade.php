@extends('layouts.base')
@section('body')
@php($user = auth()->user())
<div class="min-h-dvh lg:flex" x-data="{ nav: false }" @keydown.escape.window="nav = false">
    {{-- Seitenleiste: ab lg fest, sonst von links eingeschoben --}}
    <div x-show="nav" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" @click="nav = false" aria-hidden="true"></div>
    <aside class="sidebar fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col -translate-x-full transition-transform duration-300 lg:sticky lg:top-0 lg:z-30 lg:h-dvh lg:w-64 lg:max-w-none lg:translate-x-0 lg:shrink-0"
           :class="{ '!translate-x-0': nav }" aria-label="Hauptnavigation">
        <div class="flex items-center justify-between gap-2 px-4 pb-3 pt-[calc(1rem+env(safe-area-inset-top))]">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3 font-bold tracking-tight">
                @if ($brand['logo'])
                    <img src="{{ Storage::disk('public')->url($brand['logo']) }}" alt="" class="h-8 w-auto">
                @else
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand text-sm font-bold text-white shadow-lg shadow-brand/30">T</span>
                @endif
                <span class="truncate">{{ $brand['name'] }}</span>
            </a>
            <button type="button" class="icon-btn lg:hidden" @click="nav = false" aria-label="Menü schliessen"><x-icon name="x" /></button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 pb-4 [scrollbar-width:thin]">
            @hasSection('subnav')
                @yield('subnav')
            @else
                <x-nav-group>
                    <x-nav-item :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">Übersicht</x-nav-item>
                    <x-nav-item :href="route('account.edit')" icon="user" :active="request()->routeIs('account.*')">Mein Konto</x-nav-item>
                </x-nav-group>
                @can('platform-admin')
                    <x-nav-group label="Plattform">
                        <x-nav-item :href="route('admin.dashboard')" icon="shield">Administration</x-nav-item>
                    </x-nav-group>
                @endcan
            @endif
        </nav>

        <div class="border-t border-slate-200/70 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
            <div class="flex items-center gap-2">
                <a href="{{ route('account.edit') }}" class="nav-item min-w-0 flex-1 !py-1.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand/15 text-xs font-bold text-brand">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-900">{{ $user->name }}</span><span class="block truncate text-xs text-slate-500">{{ $user->email }}</span></span>
                </a>
                <x-theme-toggle />
            </div>
            <div class="mt-1 flex items-center justify-between">
                @can('platform-admin')<a href="{{ route('admin.dashboard') }}" class="nav-item !w-auto !gap-1.5 !py-1.5 text-xs"><x-icon name="shield" :size="14" />Administration</a>@else<span></span>@endcan
                <form method="POST" action="{{ route('logout') }}" data-logout>@csrf
                    <button class="nav-item !w-auto !gap-1.5 !py-1.5 text-xs"><x-icon name="logout" :size="14" />Abmelden</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="min-w-0 flex-1">
        {{-- Kopfleiste nur auf kleinen Bildschirmen --}}
        <header class="app-header flex items-center gap-3 border-b border-slate-200/70 px-4 py-2.5 lg:hidden">
            <button type="button" class="icon-btn" @click="nav = true" aria-label="Menü öffnen" :aria-expanded="nav"><x-icon name="menu" /></button>
            <span class="min-w-0 flex-1 truncate font-semibold tracking-tight">@yield('title', $brand['name'])</span>
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

        <main class="rise mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8 lg:py-10">
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>
@endsection
