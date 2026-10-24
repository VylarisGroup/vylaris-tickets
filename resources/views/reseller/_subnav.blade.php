<x-subnav-link :href="route('reseller.dashboard', $reseller)" :active="request()->routeIs('reseller.dashboard')">Übersicht</x-subnav-link>
<x-subnav-link :href="route('reseller.organizers.index', $reseller)" :active="request()->routeIs('reseller.organizers.*')">Veranstalter</x-subnav-link>
<x-subnav-link :href="route('reseller.branding.edit', $reseller)" :active="request()->routeIs('reseller.branding.*')">Branding</x-subnav-link>
