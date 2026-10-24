<x-subnav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">Übersicht</x-subnav-link>
<x-subnav-link :href="route('admin.resellers.index')" :active="request()->routeIs('admin.resellers.*')">Reseller</x-subnav-link>
<x-subnav-link :href="route('admin.organizers.index')" :active="request()->routeIs('admin.organizers.*')">Veranstalter</x-subnav-link>
