@extends('install.layout')
@section('step')
<div>
    <h1 class="text-2xl font-bold">Datenbank</h1>
    <p class="mt-1 text-sm text-slate-600">Hier speichert die Plattform Veranstaltungen, Bestellungen und Tickets. Lege die Datenbank bei deinem Hoster an (Plesk: «Datenbanken → Datenbank hinzufügen») und trage die Zugangsdaten ein.</p>
</div>

<form method="POST" action="{{ route('install.database.save') }}" class="space-y-4">@csrf
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="host" label="Server" :value="$values['host']" required class="sm:col-span-2" hint="Bei Plesk meist «localhost»." />
        <x-field name="port" label="Port" :value="$values['port']" required inputmode="numeric" />
    </div>
    <x-field name="database" label="Datenbankname" :value="$values['database']" required />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="username" label="Benutzername" :value="$values['username']" required autocomplete="off" />
        <x-field name="db_password" label="Passwort" type="password" autocomplete="new-password" />
    </div>
    <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="create" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('create'))> Datenbank automatisch anlegen, falls sie fehlt (braucht entsprechende Rechte)</label>
    @if ($ready)<p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Die Tabellen sind bereits angelegt. Du kannst direkt <a class="underline" href="{{ route('install.platform') }}">weiter</a> oder die Daten erneut speichern.</p>@endif
    <div class="flex flex-wrap justify-between gap-3">
        <button name="action" value="test" class="btn btn-ghost">Verbindung testen</button>
        <button name="action" value="save" class="btn btn-primary">Speichern und Tabellen anlegen</button>
    </div>
</form>

<details class="text-sm text-slate-600"><summary class="cursor-pointer font-medium">Hilfe: Wo finde ich die Zugangsdaten?</summary>
    <p class="mt-2">Bei Plesk legst du unter «Datenbanken» eine Datenbank samt Benutzer an. Name, Benutzer und Passwort legst du dort selbst fest. Der Server ist «localhost». Das Passwort wird nur in der Datei <code>.env</code> auf deinem Server gespeichert.</p>
</details>
@endsection
