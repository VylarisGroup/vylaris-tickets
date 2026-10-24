@extends('layouts.base')
@section('body')
@php($user = auth()->user())
@php($myResellers = $user->resellers()->orderBy('name')->get())
@php($myOrganizers = $user->organizers()->orderBy('name')->get())
@php($activeOrganizer = request()->route('organizer'))
@php($activeReseller = request()->route('reseller'))
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

        <div class="relative border-t border-slate-200/70 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]" x-data="{ menu: false }" @keydown.escape.window="menu = false" @click.outside="menu = false">
            <div x-show="menu" x-cloak role="menu"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-120" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="profile-menu absolute inset-x-3 bottom-full mb-2 max-h-[75dvh] overflow-y-auto rounded-3xl p-2">
                <div class="flex items-center gap-3 rounded-2xl px-3 py-3">
                    <span class="avatar h-11 w-11 text-base">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-bold text-slate-900">{{ $user->name }}</div>
                        <div class="truncate text-xs text-slate-500">{{ $user->email }}</div>
                        @if ($user->is_platform_admin)<span class="chip mt-1 !px-2 !py-0 text-[10px] !border-brand/40 !text-brand">Plattform-Admin</span>@endif
                    </div>
                </div>

                @if ($myResellers->isNotEmpty() || $myOrganizers->isNotEmpty())
                    <div class="mt-1 border-t border-slate-200/70 pt-2">
                        <div class="nav-group-label">Mandant wechseln</div>
                        @foreach ($myResellers as $r)
                            @php($on = $activeReseller?->id === $r->id)
                            <a role="menuitem" href="{{ route('tenant.switch', ['reseller', $r->id]) }}" class="nav-item {{ $on ? 'is-active' : '' }}">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon name="building" :size="15" /></span>
                                <span class="min-w-0 flex-1"><span class="block truncate">{{ $r->name }}</span><span class="block text-[10px] font-normal uppercase tracking-wider text-slate-500">Reseller</span></span>
                                @if ($on)<x-icon name="check" :size="16" />@endif
                            </a>
                        @endforeach
                        @foreach ($myOrganizers as $o)
                            @php($on = $activeOrganizer?->id === $o->id)
                            <a role="menuitem" href="{{ route('tenant.switch', ['organizer', $o->id]) }}" class="nav-item {{ $on ? 'is-active' : '' }}">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon name="flag" :size="15" /></span>
                                <span class="min-w-0 flex-1"><span class="block truncate">{{ $o->name }}</span><span class="block text-[10px] font-normal uppercase tracking-wider text-slate-500">Veranstalter</span></span>
                                @if ($on)<x-icon name="check" :size="16" />@endif
                            </a>
                        @endforeach
                        <a role="menuitem" href="{{ route('dashboard', ['alle' => 1]) }}" class="nav-item text-slate-500"><x-icon name="list" :size="16" />Alle Mandanten</a>
                    </div>
                @endif

                <div class="mt-1 border-t border-slate-200/70 pt-2">
                    <a role="menuitem" href="{{ route('account.edit') }}" class="nav-item"><x-icon name="user" :size="16" />Mein Konto</a>
                    @can('platform-admin')<a role="menuitem" href="{{ route('admin.dashboard') }}" class="nav-item"><x-icon name="shield" :size="16" />Administration</a>@endcan
                    <div class="flex items-center justify-between rounded-xl px-3 py-1 text-sm font-medium text-slate-600"><span>Darstellung</span><x-theme-toggle /></div>
                </div>

                <div class="mt-1 border-t border-slate-200/70 pt-2">
                    <form method="POST" action="{{ route('logout') }}" data-logout>@csrf
                        <button role="menuitem" class="nav-item !text-red-500"><x-icon name="logout" :size="16" />Abmelden</button>
                    </form>
                </div>
            </div>
            <button type="button" class="profile-trigger" @click="menu = !menu" :aria-expanded="menu" aria-haspopup="menu">
                <span class="avatar h-9 w-9 text-sm">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-900">{{ $user->name }}</span><span class="block truncate text-xs text-slate-500">{{ $activeOrganizer?->name ?? $activeReseller?->name ?? $user->email }}</span></span>
                <span class="text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': menu }"><x-icon name="chevron" :size="16" /></span>
            </button>
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
