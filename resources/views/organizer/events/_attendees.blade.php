{{-- Angaben pro Ticket. Erwartet $personalize (bool) und $fields (Array der gewählten Zusatzfelder). --}}
<div x-data="{ on: @js((bool) $personalize) }" class="space-y-3">
    <label class="flex items-start gap-3 text-sm">
        <input type="hidden" name="personalize_tickets" value="0">
        <input type="checkbox" name="personalize_tickets" value="1" x-model="on" class="mt-0.5 rounded border-slate-300">
        <span><span class="font-medium">Für jedes Ticket eine Person angeben</span><br><span class="text-slate-500">Beim Kauf wird zu jedem Ticket Name und Wunschangaben abgefragt. Sie stehen auf dem Ticket und in der Teilnehmerliste.</span></span>
    </label>
    <div x-show="on" x-cloak class="ml-7 space-y-2 rounded-xl border border-slate-200 p-3 text-sm">
        <div class="text-slate-500">Name ist immer Pflicht. Zusätzlich verlangen:</div>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach (\App\Support\AttendeeFields::OPTIONAL as $key => [$label])
                <label class="flex items-center gap-2"><input type="checkbox" name="attendee_fields[]" value="{{ $key }}" @checked(in_array($key, $fields, true)) class="rounded border-slate-300">{{ $label }}</label>
            @endforeach
        </div>
        <p class="text-xs text-slate-500">Auf dem Ticket erscheinen Name, Geburtsdatum und Wohnort. E-Mail und Telefon sieht nur der Veranstalter. An der Abendkasse werden keine Angaben verlangt. Bitte beachte beim Erfassen persönlicher Daten den Datenschutz.</p>
    </div>
</div>
