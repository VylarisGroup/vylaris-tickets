{{-- Datumsauswahl. Erwartet $starts, $ends, $doors (Format JJJJ-MM-TTTHH:MM oder leer). --}}
<div x-data="eventDates(@js(['starts' => $starts, 'ends' => $ends, 'doors' => $doors]))" class="space-y-5">
    <input type="hidden" name="starts_at" :value="startsAt">
    <input type="hidden" name="ends_at" :value="endsAt">
    <input type="hidden" name="doors_open_at" :value="doorsAt">

    {{-- Beginn --}}
    <div class="space-y-2">
        <span class="label">Beginn</span>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="sr-only" for="ed-date">Datum</label><input id="ed-date" type="date" x-model="date" :min="today" required class="input" aria-label="Datum des Beginns"></div>
            <div><label class="sr-only" for="ed-time">Uhrzeit</label><input id="ed-time" type="time" x-model="time" step="300" required class="input" aria-label="Uhrzeit des Beginns"></div>
        </div>
        <div class="flex flex-wrap gap-1.5 text-xs">
            <button type="button" class="chip" @click="quickDay(0)">Heute</button>
            <button type="button" class="chip" @click="quickDay(1)">Morgen</button>
            <button type="button" class="chip" @click="nextWeekday(5)">Nächster Freitag</button>
            <button type="button" class="chip" @click="nextWeekday(6)">Nächster Samstag</button>
            <span class="mx-1 self-center text-slate-300">|</span>
            <template x-for="t in ['10:00', '14:00', '18:00', '19:00', '20:00', '21:00']" :key="t">
                <button type="button" class="chip" :class="time === t && '!border-brand !text-brand'" @click="time = t" x-text="t"></button>
            </template>
        </div>
        @error('starts_at')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- Ende --}}
    <div class="space-y-2">
        <span class="label">Ende <span class="font-normal text-slate-500">(optional)</span></span>
        <div class="flex flex-wrap gap-1.5 text-xs">
            <template x-for="m in durations" :key="m">
                <button type="button" class="chip" :class="isDuration(m) && '!border-brand !text-brand'" :disabled="!startDt" @click="setDuration(m)" x-text="m / 60 + ' Std.'"></button>
            </template>
            <button type="button" class="chip" :disabled="!startDt" @click="allDay()">Ganztägig</button>
            <button type="button" class="chip" :class="multi && '!border-brand !text-brand'" :disabled="!startDt" @click="toggleMulti()">Mehrtägig</button>
            <button type="button" class="chip" x-show="endTime" x-cloak @click="clearEnd()">Kein Ende</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div x-show="multi" x-cloak><label class="sr-only" for="ed-edate">Enddatum</label><input id="ed-edate" type="date" x-model="endDate" :min="date || today" :required="multi" class="input" aria-label="Datum des Endes"></div>
            <div><label class="sr-only" for="ed-etime">Endzeit</label><input id="ed-etime" type="time" x-model="endTime" step="300" class="input" aria-label="Uhrzeit des Endes"></div>
        </div>
        @error('ends_at')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- Einlass --}}
    <div class="space-y-2">
        <span class="label">Einlass <span class="font-normal text-slate-500">(optional)</span></span>
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <button type="button" class="chip" :class="doorsMode === 'none' && '!border-brand !text-brand'" @click="doorsMode = 'none'">Kein separater Einlass</button>
            <template x-for="m in presets" :key="m">
                <button type="button" class="chip" :class="doorsMode === String(m) && '!border-brand !text-brand'" :disabled="!startDt" @click="doorsMode = String(m)" x-text="m + ' Min. vorher'"></button>
            </template>
            <button type="button" class="chip" :class="doorsMode === 'custom' && '!border-brand !text-brand'" :disabled="!startDt" @click="doorsMode = 'custom'; doorsTime ||= time">Uhrzeit wählen</button>
        </div>
        <div x-show="doorsMode === 'custom'" x-cloak class="sm:w-1/2"><input type="time" x-model="doorsTime" step="300" class="input" aria-label="Uhrzeit des Einlasses"></div>
        @error('doors_open_at')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- Zusammenfassung --}}
    <div x-show="summary" x-cloak class="rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 text-sm" aria-live="polite">
        <div class="font-medium" x-text="summary"></div>
        <ul class="mt-1 space-y-0.5 text-xs" :class="hardError ? 'text-red-600' : 'text-amber-700'" x-show="warnings.length"><template x-for="w in warnings" :key="w"><li x-text="w"></li></template></ul>
    </div>
</div>
