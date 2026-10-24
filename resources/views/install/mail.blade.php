@extends('install.layout')
@section('step')
<div>
    <h1 class="text-2xl font-bold">E-Mail-Versand</h1>
    <p class="mt-1 text-sm text-slate-600">Über diesen Mail-Server gehen Tickets, Bestätigungen und Wartelisten-Nachrichten raus. Die Zugangsdaten bekommst du von deinem E-Mail-Anbieter (bei Plesk: Mail-Konto der Domain).</p>
</div>

<form method="POST" action="{{ route('install.mail.save') }}" class="space-y-4">@csrf
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="host" label="SMTP-Server" :value="$values['host']" required class="sm:col-span-2" placeholder="mail.meinefirma.ch" />
        <x-field name="port" label="Port" :value="$values['port']" required inputmode="numeric" />
    </div>
    <div>
        <label class="label" for="encryption">Verschlüsselung</label>
        <select id="encryption" name="encryption" class="input">
            @foreach (['tls' => 'TLS / STARTTLS (Port 587)', 'ssl' => 'SSL (Port 465)', 'none' => 'Keine (Port 25)'] as $k => $l)<option value="{{ $k }}" @selected(old('encryption', 'tls') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="username" label="Benutzername" :value="$values['username']" autocomplete="off" />
        <x-field name="mail_password" label="Passwort" type="password" autocomplete="new-password" />
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="from_address" label="Absender-Adresse" type="email" :value="$values['from_address']" required placeholder="tickets@meinefirma.ch" />
        <x-field name="from_name" label="Absender-Name" :value="$values['from_name']" required />
    </div>
    <div class="flex flex-wrap justify-between gap-3">
        <button name="action" value="test" class="btn btn-ghost">Test-E-Mail an mich senden</button>
        <div class="flex gap-2">
            <button name="action" value="skip" class="btn btn-ghost" formnovalidate>Später einrichten</button>
            <button name="action" value="save" class="btn btn-primary">Speichern und weiter</button>
        </div>
    </div>
</form>
@endsection
