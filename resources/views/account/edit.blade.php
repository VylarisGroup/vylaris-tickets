@extends('layouts.app')
@section('title', 'Mein Konto')
@section('content')
<h1 class="mb-6 text-2xl font-extrabold tracking-tight">Mein Konto</h1>
<div class="grid max-w-5xl gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('account.update') }}" class="card space-y-4">@csrf @method('PUT')
        <h2 class="font-semibold">Profil</h2>
        <x-field name="name" label="Name" :value="$user->name" required autocomplete="name" />
        <x-field name="email" label="E-Mail" type="email" :value="$user->email" required autocomplete="username" />
        <button class="btn btn-primary">Speichern</button>
    </form>
    <form method="POST" action="{{ route('account.password') }}" class="card space-y-4">@csrf @method('PUT')
        <h2 class="font-semibold">Passwort ändern</h2>
        <x-field name="current_password" label="Aktuelles Passwort" type="password" required autocomplete="current-password" />
        <x-field name="password" label="Neues Passwort" type="password" required autocomplete="new-password" hint="Mindestens 10 Zeichen." />
        <x-field name="password_confirmation" label="Neues Passwort wiederholen" type="password" required autocomplete="new-password" />
        <button class="btn btn-primary">Passwort ändern</button>
    </form>
    <div class="card lg:col-span-2">
        <h2 class="mb-3 font-semibold">Meine Zugänge</h2>
        <div class="grid gap-2 sm:grid-cols-2">
            @if ($user->is_platform_admin)<a href="{{ route('admin.dashboard') }}" class="nav-item border border-slate-200"><x-icon name="shield" />Plattform-Administration</a>@endif
            @foreach ($resellers as $r)<a href="{{ route('reseller.dashboard', $r) }}" class="nav-item border border-slate-200"><x-icon name="building" />{{ $r->name }}<span class="chip ml-auto">Reseller</span></a>@endforeach
            @foreach ($organizers as $o)<a href="{{ route('organizer.dashboard', $o) }}" class="nav-item border border-slate-200"><x-icon name="calendar" />{{ $o->name }}<span class="chip ml-auto">Veranstalter</span></a>@endforeach
        </div>
    </div>
</div>
@endsection
