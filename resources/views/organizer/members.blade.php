@extends('layouts.app')
@section('title', 'Team')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">Team</h1>
@error('role')<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror
<div class="card mb-8 divide-y divide-slate-100 p-0">
    @foreach ($members as $m)
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
            <div><div class="font-medium">{{ $m->name }}</div><div class="text-sm text-slate-500">{{ $m->email }}</div></div>
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('organizer.members.update', [$organizer, $m]) }}" class="flex items-center gap-2">@csrf @method('PUT')
                    <select name="role" class="input w-auto py-1.5" onchange="this.form.submit()">
                        @foreach ($roles as $r)<option value="{{ $r->value }}" @selected($m->pivot->role === $r->value)>{{ $r->label() }}</option>@endforeach
                    </select>
                </form>
                <form method="POST" action="{{ route('organizer.members.destroy', [$organizer, $m]) }}" onsubmit="return confirm('Mitglied entfernen?')">@csrf @method('DELETE')
                    <button class="text-sm text-red-600">Entfernen</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
<form method="POST" action="{{ route('organizer.members.store', $organizer) }}" class="card max-w-2xl space-y-4">@csrf
    <h2 class="font-semibold">Mitglied hinzufügen</h2>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="name" label="Name" required />
        <x-field name="email" label="E-Mail" type="email" required />
    </div>
    <div>
        <label class="label" for="role">Rolle</label>
        <select id="role" name="role" class="input">
            @foreach ($roles as $r)<option value="{{ $r->value }}" @selected(old('role', 'scanner') === $r->value)>{{ $r->label() }}</option>@endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Einlass: nur scannen. Kasse: zusätzlich Tickets verkaufen. Verwalter: Events, Team und Einstellungen.</p>
    </div>
    <x-field name="password" label="Passwort" type="password" hint="Nur nötig, wenn die E-Mail noch kein Konto hat. Mindestens 10 Zeichen." />
    <button class="btn btn-primary">Hinzufügen</button>
</form>
@endsection
