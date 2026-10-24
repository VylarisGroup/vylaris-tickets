@extends('install.layout')
@section('step')
<div>
    <h1 class="text-2xl font-bold">Administrator</h1>
    <p class="mt-1 text-sm text-slate-600">Dieses Konto verwaltet die ganze Plattform: Reseller, Veranstalter, Meldungen und Updates.</p>
</div>

@if ($exists && $loggedIn)
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Der Administrator ist angelegt und du bist angemeldet.</p>
    <div class="flex justify-end"><a href="{{ route('install.mail') }}" class="btn btn-primary">Weiter</a></div>
@elseif ($exists)
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Die Einrichtung läuft bereits. Melde dich mit dem Administrator-Konto an, um fortzufahren.</p>
    <form method="POST" action="{{ route('install.login') }}" class="space-y-4">@csrf
        <x-field name="email" label="E-Mail" type="email" required autocomplete="username" />
        <x-field name="password" label="Passwort" type="password" required autocomplete="current-password" />
        <div class="flex justify-end"><button class="btn btn-primary">Anmelden und fortfahren</button></div>
    </form>
@else
    <form method="POST" action="{{ route('install.admin.save') }}" class="space-y-4">@csrf
        <x-field name="name" label="Name" required autocomplete="name" />
        <x-field name="email" label="E-Mail" type="email" required autocomplete="username" hint="Damit meldest du dich an." />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="password" label="Passwort" type="password" required autocomplete="new-password" hint="Mindestens 10 Zeichen." />
            <x-field name="password_confirmation" label="Passwort wiederholen" type="password" required autocomplete="new-password" />
        </div>
        <div class="flex justify-end"><button class="btn btn-primary">Administrator anlegen</button></div>
    </form>
@endif
@endsection
