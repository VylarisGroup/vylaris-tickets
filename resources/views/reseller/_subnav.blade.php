<div class="mb-4 rounded-2xl border border-slate-200 p-3" style="background-color: color-mix(in oklab, var(--brand-ui) 6%, transparent)">
    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Reseller</div>
    <div class="truncate font-semibold tracking-tight">{{ $reseller->name }}</div>
    @if (auth()->user()->is_platform_admin || auth()->user()->organizers()->count() + auth()->user()->resellers()->count() > 1)
        <a href="{{ route('dashboard') }}" class="mt-0.5 inline-flex items-center gap-1 text-xs text-slate-500 hover:text-brand"><x-icon name="arrow-left" :size="12" />Wechseln</a>
    @endif
</div>
<x-nav-group>
    <x-nav-item :href="route('reseller.dashboard', $reseller)" icon="home" :active="request()->routeIs('reseller.dashboard')">Übersicht</x-nav-item>
</x-nav-group>
<x-nav-group label="Kunden">
    <x-nav-item :href="route('reseller.organizers.index', $reseller)" icon="building" :active="request()->routeIs('reseller.organizers.*')">Veranstalter</x-nav-item>
</x-nav-group>
<x-nav-group label="Auftritt">
    <x-nav-item :href="route('reseller.branding.edit', $reseller)" icon="palette" :active="request()->routeIs('reseller.branding.*')">Branding</x-nav-item>
    <x-nav-item :href="route('reseller.domains.index', $reseller)" icon="globe" :active="request()->routeIs('reseller.domains.*')">Eigene Domains</x-nav-item>
</x-nav-group>
