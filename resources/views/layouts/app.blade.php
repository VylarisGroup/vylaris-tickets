@extends('layouts.base')
@section('body')
<header class="border-b border-slate-200 bg-white" x-data="{ open: false }">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-bold">
            @if ($brand['logo'])
                <img src="{{ Storage::disk('public')->url($brand['logo']) }}" alt="" class="h-8 w-auto">
            @else
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-sm text-white">T</span>
            @endif
            <span>{{ $brand['name'] }}</span>
        </a>
        <nav class="flex items-center gap-4 text-sm">
            @can('platform-admin')
                <a href="{{ route('admin.dashboard') }}" class="font-medium text-slate-600 hover:text-brand">Administration</a>
            @endcan
            <span class="hidden text-slate-500 sm:inline">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" data-logout>@csrf
                <button class="font-medium text-slate-600 hover:text-brand">Abmelden</button>
            </form>
        </nav>
    </div>
    @hasSection('subnav')
        <div class="border-t border-slate-100 bg-white">
            <div class="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-4 text-sm">@yield('subnav')</div>
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
<main class="mx-auto max-w-6xl px-4 py-8">
    <x-flash />
    @yield('content')
</main>
@endsection
