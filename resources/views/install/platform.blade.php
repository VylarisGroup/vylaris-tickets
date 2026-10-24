@extends('install.layout')
@section('step')
<div>
    <h1 class="text-2xl font-bold">Plattform</h1>
    <p class="mt-1 text-sm text-slate-600">Name und Aussehen deiner Ticket-Plattform. Alles lässt sich später ändern.</p>
</div>

<form method="POST" action="{{ route('install.platform.save') }}" class="space-y-4">@csrf
    <x-field name="name" label="Name der Plattform" :value="$values['name']" required />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="color" label="Markenfarbe" :value="$values['color']" required hint="Hex-Wert, z. B. #1867F8." />
        <x-field name="url" label="Adresse der Plattform" type="url" :value="$values['url']" required hint="So erreichen Besucher die Seite. Wir haben sie automatisch erkannt." />
    </div>
    <x-field name="base_domain" label="Basisdomain für Reseller (optional)" :value="$values['base_domain']" placeholder="tickets.meinefirma.ch" hint="Nur nötig, wenn Reseller Subdomains wie «kunde.tickets.meinefirma.ch» erhalten sollen. Eigene Domains der Reseller funktionieren auch ohne." />
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_registration" value="1" class="rounded border-slate-300" @checked(old('allow_registration', true))> Veranstalter dürfen sich selbst registrieren</label>
    <div class="flex justify-end"><button class="btn btn-primary">Weiter</button></div>
</form>
@endsection
