@extends('layouts.guest')
@section('title', 'Anmelden')
@section('content')
<h1 class="mb-6 text-xl font-bold">Anmelden</h1>
<form method="POST" action="{{ route('login') }}" class="space-y-4">@csrf
    <x-field name="email" label="E-Mail" type="email" required autofocus autocomplete="username" />
    <x-field name="password" label="Passwort" type="password" required autocomplete="current-password" />
    <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" class="rounded border-slate-300"> Angemeldet bleiben</label>
    <button class="btn btn-primary w-full">Anmelden</button>
</form>
@if ($brand['reseller'] ? $brand['reseller']->allow_registration : config('tickets.allow_registration'))
    <p class="mt-6 text-center text-sm text-slate-500">Noch kein Konto? <a href="{{ route('register') }}" class="font-medium text-brand">Als Veranstalter registrieren</a></p>
@endif
@endsection
