@extends('layouts.app')
@section('title', 'Veranstalter anlegen')
@section('subnav') @include('reseller._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">Veranstalter anlegen</h1>
<form method="POST" action="{{ route('reseller.organizers.store', $reseller) }}" class="card max-w-2xl space-y-4">@csrf
    <x-field name="name" label="Name des Veranstalters" required />
    <x-field name="contact_email" label="Kontakt-E-Mail" type="email" />
    <hr class="border-slate-200">
    <h2 class="font-semibold">Inhaber</h2>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="owner_name" label="Name" required />
        <x-field name="owner_email" label="E-Mail" type="email" required />
    </div>
    <x-field name="owner_password" label="Passwort" type="password" hint="Nur nötig, wenn die E-Mail noch kein Konto hat. Mindestens 10 Zeichen." />
    <div class="flex gap-3">
        <button class="btn btn-primary">Anlegen</button>
        <a href="{{ route('reseller.organizers.index', $reseller) }}" class="btn btn-ghost">Abbrechen</a>
    </div>
</form>
@endsection
