@extends('layouts.guest')
@section('title', 'Einladung annehmen')
@section('content')
<h1 class="mb-2 text-xl font-bold">Einladung annehmen</h1>
<p class="mb-6 text-sm text-slate-500">Du wurdest eingeladen, <strong>{{ $organizer->name }}</strong> zu verwalten. Lege dein Passwort fest.</p>
<form method="POST" action="{{ route('invitation.accept', $token) }}" class="space-y-4">@csrf
    <x-field name="email" label="E-Mail" type="email" :value="$invitation->email" disabled />
    <x-field name="name" label="Dein Name" :value="$invitation->name" required autocomplete="name" />
    <x-field name="password" label="Passwort" type="password" required autocomplete="new-password" hint="Mindestens 10 Zeichen." />
    <x-field name="password_confirmation" label="Passwort wiederholen" type="password" required autocomplete="new-password" />
    <button class="btn btn-primary w-full">Konto einrichten</button>
</form>
@endsection
