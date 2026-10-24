@php($dt = fn ($d) => $d?->format('Y-m-d\TH:i'))
@php($p = $t ? '' : 'new_')
<div class="grid gap-4 sm:grid-cols-3">
    <x-field name="name" label="Name" :value="$t?->name" required class="sm:col-span-2" />
    <x-field name="price" :label="'Preis ('.$currency.')'" :value="$t ? \App\Support\Money::input($t->price_cents) : ''" placeholder="25.00" required />
</div>
<x-field name="description" label="Beschreibung (optional)" :value="$t?->description" />
<div class="grid gap-4 sm:grid-cols-2">
    <x-field name="quota" label="Anzahl Plätze" type="number" min="1" :value="$t?->quota" hint="Leer = unbegrenzt." />
    <x-field name="max_per_order" label="Max. pro Bestellung" type="number" min="1" max="100" :value="$t?->max_per_order ?? 10" required />
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <x-field name="sales_start_at" label="Verkauf ab" type="datetime-local" :value="$dt($t?->sales_start_at)" />
    <x-field name="sales_end_at" label="Verkauf bis" type="datetime-local" :value="$dt($t?->sales_end_at)" />
</div>
<label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($t ? $t->is_active : true) class="rounded border-slate-300"> Aktiv</label>
