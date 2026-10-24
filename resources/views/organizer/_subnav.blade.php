<x-subnav-link :href="route('organizer.dashboard', $organizer)" :active="request()->routeIs('organizer.dashboard')">Übersicht</x-subnav-link>
@can('sell-organizer', $organizer)
    <x-subnav-link :href="route('organizer.door.index', $organizer)" :active="request()->routeIs('organizer.door.*')">Abendkasse</x-subnav-link>
@endcan
@can('scan-organizer', $organizer)
    <x-subnav-link :href="route('organizer.scan.index', $organizer)" :active="request()->routeIs('organizer.scan.*')">Einlass</x-subnav-link>
@endcan
@can('manage-organizer', $organizer)
    <x-subnav-link :href="route('organizer.events.index', $organizer)" :active="request()->routeIs('organizer.events.*') || request()->routeIs('organizer.tickets.*')">Events</x-subnav-link>
    <x-subnav-link :href="route('organizer.vouchers.index', $organizer)" :active="request()->routeIs('organizer.vouchers.*')">Gutscheine</x-subnav-link>
    <x-subnav-link :href="route('organizer.members.index', $organizer)" :active="request()->routeIs('organizer.members.*')">Team</x-subnav-link>
    <x-subnav-link :href="route('organizer.settings.edit', $organizer)" :active="request()->routeIs('organizer.settings.*')">Einstellungen</x-subnav-link>
@endcan
