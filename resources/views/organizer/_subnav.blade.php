<x-subnav-link :href="route('organizer.dashboard', $organizer)" :active="request()->routeIs('organizer.dashboard')">Übersicht</x-subnav-link>
@can('manage-organizer', $organizer)
    <x-subnav-link :href="route('organizer.members.index', $organizer)" :active="request()->routeIs('organizer.members.*')">Team</x-subnav-link>
    <x-subnav-link :href="route('organizer.settings.edit', $organizer)" :active="request()->routeIs('organizer.settings.*')">Einstellungen</x-subnav-link>
@endcan
