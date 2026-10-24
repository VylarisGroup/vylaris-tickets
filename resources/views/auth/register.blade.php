@extends('layouts.guest')
@section('title', 'Registrieren')
@section('content')
<h1 class="mb-6 text-xl font-bold">Als Veranstalter registrieren</h1>
<form method="POST" action="{{ route('register') }}" class="space-y-4">@csrf
    <x-field name="organizer_name" label="Name des Veranstalters / Vereins" required />
    <x-field name="name" label="Dein Name" required autocomplete="name" />
    <x-field name="email" label="E-Mail" type="email" required autocomplete="username" />
    <x-field name="password" label="Passwort" type="password" required autocomplete="new-password" hint="Mindestens 10 Zeichen." />
    <x-field name="password_confirmation" label="Passwort wiederholen" type="password" required autocomplete="new-password" />
    <button class="btn btn-primary w-full">Konto erstellen</button>
</form>
<p class="mt-6 text-center text-sm text-slate-500">Schon registriert? <a href="{{ route('login') }}" class="font-medium text-brand">Anmelden</a></p>
@endsection
