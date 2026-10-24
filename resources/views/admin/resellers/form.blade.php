@extends('layouts.app')
@section('title', $reseller->exists ? 'Reseller bearbeiten' : 'Reseller anlegen')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">{{ $reseller->exists ? 'Reseller bearbeiten' : 'Reseller anlegen' }}</h1>
<form method="POST" action="{{ $reseller->exists ? route('admin.resellers.update', $reseller) : route('admin.resellers.store') }}" class="card max-w-2xl space-y-4">
    @csrf
    @if ($reseller->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="name" label="Name" :value="$reseller->name" required />
        <x-field name="slug" label="Kürzel" :value="$reseller->slug" required hint="Kurzname, z. B. meinverein." />
    </div>
    <x-field name="domain" label="Eigene Domain (optional)" :value="$reseller->domain" hint="Nur vom Betreiber einzurichten: Die Domain muss auf diese Installation zeigen und im Webserver samt Zertifikat eingerichtet sein." />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="brand_name" label="Anzeigename (Whitelabel)" :value="$reseller->brand_name" />
        <x-field name="brand_color" label="Markenfarbe" :value="$reseller->brand_color" placeholder="#1867F8" />
    </div>
    <x-field name="support_email" label="Support-E-Mail" type="email" :value="$reseller->support_email" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="max_organizers" label="Max. Veranstalter" type="number" min="0" :value="$reseller->max_organizers" hint="Leer = unbegrenzt." />
        <x-field name="max_events_per_organizer" label="Max. Events pro Veranstalter" type="number" min="0" :value="$reseller->max_events_per_organizer" hint="Leer = unbegrenzt." />
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_registration" value="1" @checked(old('allow_registration', $reseller->allow_registration)) class="rounded border-slate-300"> Registrierung neuer Veranstalter erlauben</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $reseller->is_active)) class="rounded border-slate-300"> Aktiv</label>

    @unless ($reseller->exists)
        <hr class="border-slate-200">
        <h2 class="font-semibold">Inhaber des Resellers</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="owner_name" label="Name" required />
            <x-field name="owner_email" label="E-Mail" type="email" required />
        </div>
        <x-field name="owner_password" label="Passwort" type="password" hint="Nur nötig, wenn die E-Mail noch kein Konto hat. Mindestens 10 Zeichen." />
    @endunless

    <div class="flex gap-3">
        <button class="btn btn-primary">Speichern</button>
        <a href="{{ route('admin.resellers.index') }}" class="btn btn-ghost">Abbrechen</a>
    </div>
</form>
@if ($reseller->exists)
    <form method="POST" action="{{ route('admin.resellers.destroy', $reseller) }}" class="mt-6" onsubmit="return confirm('Reseller wirklich löschen?')">@csrf @method('DELETE')
        <button class="btn btn-danger">Reseller löschen</button>
    </form>
@endif
@endsection
