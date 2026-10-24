@props(['label' => null])
<div class="mt-5 first:mt-0">
    @if ($label)<div class="nav-group-label">{{ $label }}</div>@endif
    <div class="space-y-0.5">{{ $slot }}</div>
</div>
