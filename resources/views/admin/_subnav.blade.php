<x-subnav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">Übersicht</x-subnav-link>
<x-subnav-link :href="route('admin.resellers.index')" :active="request()->routeIs('admin.resellers.*')">Reseller</x-subnav-link>
<x-subnav-link :href="route('admin.organizers.index')" :active="request()->routeIs('admin.organizers.*')">Veranstalter</x-subnav-link>
<x-subnav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')">Meldungen @if (($openReports ?? 0) > 0)<span class="ml-1 rounded-full bg-[#dc2626] px-1.5 text-xs text-white">{{ $openReports }}</span>@endif</x-subnav-link>
<x-subnav-link :href="route('admin.update')" :active="request()->routeIs('admin.update*')">Updates</x-subnav-link>
