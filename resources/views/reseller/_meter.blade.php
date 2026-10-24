{{-- Fortschrittsanzeige für ein Limit. Erwartet $label, $used und $max (null = unbegrenzt). --}}
@php($pct = \App\Models\Reseller::percent($used, $max))
@php($tone = $pct === null ? 'bg-brand' : ($pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-emerald-500')))
<div>
    <div class="flex items-baseline justify-between text-sm"><span class="text-slate-500">{{ $label }}</span>
        <span class="font-semibold">{{ $used }}@if ($max !== null)<span class="font-normal text-slate-400"> / {{ $max }}</span>@else<span class="font-normal text-slate-400"> · unbegrenzt</span>@endif</span></div>
    @if ($pct !== null)
        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $label }}"><div class="h-full rounded-full {{ $tone }}" style="width: {{ $pct }}%"></div></div>
        @if ($pct >= 100)<div class="mt-1 text-xs text-red-600">Limit erreicht.</div>
        @elseif ($pct >= 80)<div class="mt-1 text-xs text-amber-700">Fast erreicht, noch {{ $max - $used }} frei.</div>@endif
    @endif
</div>
