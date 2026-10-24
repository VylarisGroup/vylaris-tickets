<div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Ebenen" x-show="floors.length > 1" x-cloak>
    <template x-for="f in floors" :key="f.id">
        <button type="button" role="tab" :aria-selected="floor && floor.id === f.id" @click="floorId = f.id; picker = null" class="btn !py-1.5 text-sm" :class="floor && floor.id === f.id ? 'btn-primary' : 'btn-ghost'">
            <span x-text="f.name"></span>
            <span class="ml-1.5 rounded-full bg-white/25 px-1.5 text-xs" x-show="floorSelected(f)" x-text="floorSelected(f)"></span>
        </button>
    </template>
</div>
<div class="flex flex-wrap items-center gap-3 text-xs">
    <template x-for="s in sections" :key="s.id">
        <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" :style="`background:${s.color}`"></span><span x-text="s.name + (s.price ? ' · ' + s.price + (seats.some((x) => x.section === s.id && x.kind === 'table') ? ' pro Platz' : '') : '')"></span></span>
    </template>
    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-slate-300"></span>vergeben</span>
    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full border-2 border-slate-900 bg-slate-900"></span>deine Wahl</span>
    <span class="ml-auto flex items-center gap-1">
        <button type="button" class="btn btn-ghost !px-2 !py-1" @click="zoom = Math.max(1, zoom - .5)" aria-label="Verkleinern">−</button>
        <button type="button" class="btn btn-ghost !px-2 !py-1" @click="zoom = Math.min(4, zoom + .5)" aria-label="Vergrössern">+</button>
    </span>
</div>
<div class="overflow-auto rounded-lg border border-slate-200 bg-white" style="max-height:70vh">
    <svg :view-box.camel="viewBox" @click="svgClick($event)" class="mx-auto block" :style="`width:${zoom * 100}%;min-height:240px`" role="img" aria-label="Sitzplan">
        <image x-show="floor && floor.plan" :href="floor?.plan || ''" x="0" y="0" :width="floor?.w || 1000" :height="floor?.h || 640" preserveAspectRatio="none" opacity=".9"/>
        <g x-html="markup"></g>
    </svg>
</div>
<div class="rounded-lg border border-brand/40 bg-brand/5 p-3 text-sm" x-show="picker" x-cloak>
    <template x-if="picker">
        <div class="flex flex-wrap items-center gap-3">
            <div><span class="font-medium" x-text="picker.label"></span> <span class="text-slate-500" x-text="'· ' + picker.free + ' von ' + picker.capacity + ' Plätzen frei'"></span></div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn btn-ghost h-10 w-10 text-lg" @click="setQty(picker, (sel[picker.id] || 0) - 1)" aria-label="Weniger">−</button>
                <span class="w-8 text-center text-lg font-semibold" x-text="sel[picker.id] || 0"></span>
                <button type="button" class="btn btn-ghost h-10 w-10 text-lg" @click="setQty(picker, (sel[picker.id] || 0) + 1)" aria-label="Mehr">+</button>
            </div>
            <span class="text-slate-500">Plätze am Tisch</span>
            <button type="button" class="btn btn-ghost ml-auto !py-1.5" @click="picker = null">Fertig</button>
        </div>
    </template>
</div>
<div class="space-y-1 text-sm" x-show="selectedSeats.length" x-cloak>
    <span class="font-medium">Gewählt:</span>
    <template x-for="seat in selectedSeats" :key="seat.id">
        <span class="mr-2 inline-flex items-center gap-1.5 rounded bg-slate-100 px-2 py-0.5">
            <span x-text="seat.label + (isTable(seat) ? ' · ' + qtyOf(seat) + (qtyOf(seat) === 1 ? ' Platz' : ' Plätze') : '')"></span>
            <button type="button" class="text-slate-500 hover:text-red-600" @click="remove(seat)" aria-label="Entfernen">✕</button>
        </span>
    </template>
</div>
<template x-for="v in seatParams" :key="v"><input type="hidden" name="seats[]" :value="v"></template>
