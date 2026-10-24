<div class="mb-4 rounded-2xl border border-slate-200 p-3" style="background-color: color-mix(in oklab, var(--brand-ui) 6%, transparent)">
    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Veranstalter</div>
    <div class="truncate font-semibold tracking-tight">{{ $organizer->name }}</div>
    @if (auth()->user()->is_platform_admin || auth()->user()->organizers()->count() + auth()->user()->resellers()->count() > 1)
        <a href="{{ route('dashboard') }}" class="mt-0.5 inline-flex items-center gap-1 text-xs text-slate-500 hover:text-brand"><x-icon name="arrow-left" :size="12" />Wechseln</a>
    @endif
</div>
<x-nav-group>
    <x-nav-item :href="route('organizer.dashboard', $organizer)" icon="home" :active="request()->routeIs('organizer.dashboard')">Übersicht</x-nav-item>
</x-nav-group>
@can('manage-organizer', $organizer)
    <x-nav-group label="Veranstaltungen">
        <x-nav-item :href="route('organizer.events.index', $organizer)" icon="calendar" :active="request()->routeIs('organizer.events.index', 'organizer.events.show', 'organizer.events.edit', 'organizer.events.create', 'organizer.tickets.*', 'organizer.seating.*', 'organizer.attendees.*', 'organizer.messages.*')">Events</x-nav-item>
        <x-nav-item :href="route('organizer.wizard', $organizer)" icon="wand" :active="request()->routeIs('organizer.wizard*')">Event-Assistent</x-nav-item>
        <x-nav-item :href="route('organizer.orders.all', $organizer)" icon="list" :active="request()->routeIs('organizer.orders.*')">Bestellungen</x-nav-item>
        <x-nav-item :href="route('organizer.vouchers.index', $organizer)" icon="tag" :active="request()->routeIs('organizer.vouchers.*')">Gutscheine</x-nav-item>
    </x-nav-group>
@endcan
@if (auth()->user()->can('sell-organizer', $organizer) || auth()->user()->can('scan-organizer', $organizer))
    <x-nav-group label="Vor Ort">
        @can('sell-organizer', $organizer)
            <x-nav-item :href="route('organizer.door.index', $organizer)" icon="cash" :active="request()->routeIs('organizer.door.*')">Abendkasse</x-nav-item>
        @endcan
        @can('scan-organizer', $organizer)
            <x-nav-item :href="route('organizer.scan.index', $organizer)" icon="scan" :active="request()->routeIs('organizer.scan.*')">Einlass</x-nav-item>
        @endcan
    </x-nav-group>
@endif
@can('manage-organizer', $organizer)
    <x-nav-group label="Auswertung">
        <x-nav-item :href="route('organizer.stats', $organizer)" icon="chart" :active="request()->routeIs('organizer.stats*')">Statistik</x-nav-item>
        <x-nav-item :href="route('organizer.customers.index', $organizer)" icon="user" :active="request()->routeIs('organizer.customers.*')">Kunden</x-nav-item>
    </x-nav-group>
    <x-nav-group label="Verwaltung">
        <x-nav-item :href="route('organizer.members.index', $organizer)" icon="users" :active="request()->routeIs('organizer.members.*')">Team</x-nav-item>
        <x-nav-item :href="route('organizer.settings.edit', $organizer)" icon="settings" :active="request()->routeIs('organizer.settings.*')">Einstellungen</x-nav-item>
    </x-nav-group>
@endcan
<x-nav-group label="Öffentlich">
    <x-nav-item :href="route('shop.organizer', $organizer)" icon="globe" :external="true">Meine Eventseite</x-nav-item>
</x-nav-group>
