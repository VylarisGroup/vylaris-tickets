@props(['href', 'icon' => null, 'active' => false, 'badge' => null, 'external' => false])
<a href="{{ $href }}" @if ($active) aria-current="page" @endif @if ($external) target="_blank" rel="noopener" @endif class="nav-item {{ $active ? 'is-active' : '' }}">
    @if ($icon)<x-icon :name="$icon" />@endif
    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    @if ($badge)<span class="rounded-full bg-[#dc2626] px-1.5 py-0.5 text-[10px] font-bold leading-none text-white">{{ $badge }}</span>@endif
    @if ($external)<x-icon name="external" :size="14" class="opacity-50" />@endif
</a>
