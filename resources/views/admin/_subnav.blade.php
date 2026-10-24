<div class="mb-4 rounded-2xl border border-slate-200 p-3" style="background-color: color-mix(in oklab, var(--brand-ui) 6%, transparent)">
    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Plattform</div>
    <div class="truncate font-semibold tracking-tight">Administration</div>
    <a href="{{ route('dashboard') }}" class="mt-0.5 inline-flex items-center gap-1 text-xs text-slate-500 hover:text-brand"><x-icon name="arrow-left" :size="12" />Zur Übersicht</a>
</div>
<x-nav-group>
    <x-nav-item :href="route('admin.dashboard')" icon="home" :active="request()->routeIs('admin.dashboard')">Übersicht</x-nav-item>
</x-nav-group>
<x-nav-group label="Mandanten">
    <x-nav-item :href="route('admin.resellers.index')" icon="building" :active="request()->routeIs('admin.resellers.*')">Reseller</x-nav-item>
    <x-nav-item :href="route('admin.organizers.index')" icon="calendar" :active="request()->routeIs('admin.organizers.*')">Veranstalter</x-nav-item>
    <x-nav-item :href="route('admin.users.index')" icon="users" :active="request()->routeIs('admin.users.*')">Benutzer</x-nav-item>
</x-nav-group>
<x-nav-group label="Moderation">
    <x-nav-item :href="route('admin.reports.index')" icon="flag" :active="request()->routeIs('admin.reports.*')" :badge="($openReports ?? 0) > 0 ? $openReports : null">Meldungen</x-nav-item>
</x-nav-group>
<x-nav-group label="System">
    <x-nav-item :href="route('admin.update')" icon="download" :active="request()->routeIs('admin.update*')">Updates</x-nav-item>
</x-nav-group>
