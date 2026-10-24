<div class="flex flex-wrap gap-3 text-xs">
    <template x-for="s in sections" :key="s.id">
        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" :style="`background:${s.color}`"></span><span x-text="s.name + (s.price ? ' · ' + s.price : '')"></span></span>
    </template>
    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-slate-300"></span>vergeben</span>
    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full border-2 border-slate-900 bg-slate-900"></span>deine Wahl</span>
</div>
<div class="overflow-auto rounded-lg border border-slate-200 bg-slate-50">
    <svg :viewBox="viewBox" class="mx-auto block w-full" style="min-height:240px;max-height:560px" role="img" aria-label="Sitzplan">
        <template x-for="seat in seats" :key="seat.id">
            <g @click="toggle(seat)" :class="seat.taken || !sellable(seat) ? 'cursor-not-allowed' : 'cursor-pointer'">
                <circle :cx="seat.x" :cy="seat.y" r="11"
                        :fill="seat.taken || !sellable(seat) ? '#cbd5e1' : (isSelected(seat) ? '#0f172a' : color(seat))"
                        :stroke="isSelected(seat) ? '#0f172a' : 'none'" stroke-width="3"><title x-text="seat.label + (seat.taken ? ' (vergeben)' : '')"></title></circle>
            </g>
        </template>
    </svg>
</div>
<div class="text-sm" x-show="selected.length" x-cloak>
    <span class="font-medium">Gewählt:</span>
    <template x-for="id in selected" :key="id"><span class="mr-2 inline-block rounded bg-slate-100 px-2 py-0.5" x-text="byId(id).label"></span></template>
</div>
<template x-for="id in selected" :key="'i'+id"><input type="hidden" name="seats[]" :value="id"></template>
