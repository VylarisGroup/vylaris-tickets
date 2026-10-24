@extends('layouts.app')
@section('title', $user->exists ? 'Benutzer bearbeiten' : 'Benutzer anlegen')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand"><x-icon name="arrow-left" :size="14" />Benutzer</a>
<h1 class="mb-6 mt-2 text-2xl font-extrabold tracking-tight">{{ $user->exists ? 'Benutzer bearbeiten' : 'Benutzer anlegen' }}</h1>
<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card max-w-2xl space-y-4">@csrf
    @if ($user->exists) @method('PUT') @endif
    <x-field name="name" label="Name" :value="$user->name" required />
    <x-field name="email" label="E-Mail" type="email" :value="$user->email" required />
    <x-field name="password" :label="$user->exists ? 'Neues Passwort (leer = unverändert)' : 'Passwort'" type="password" :required="! $user->exists" autocomplete="new-password" hint="Mindestens 10 Zeichen." />
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_platform_admin" value="1" @checked(old('is_platform_admin', $user->is_platform_admin)) class="rounded border-slate-300"> Plattform-Administrator</label>
    @error('is_platform_admin')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @if ($user->exists)
        <div class="rounded-xl border border-slate-200 p-4 text-sm">
            <div class="mb-2 font-semibold">Zugänge</div>
            @forelse ($resellers as $r)<div class="flex justify-between"><span>{{ $r->name }}</span><span class="text-slate-500">Reseller · {{ $r->pivot->role }}</span></div>@empty @endforelse
            @forelse ($organizers as $o)<div class="flex justify-between"><span>{{ $o->name }}</span><span class="text-slate-500">Veranstalter · {{ $o->pivot->role }}</span></div>@empty @endforelse
            @if ($resellers->isEmpty() && $organizers->isEmpty())<span class="text-slate-500">Keine Zuordnungen.</span>@endif
        </div>
    @endif
    <div class="flex gap-3"><button class="btn btn-primary">Speichern</button><a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Abbrechen</a></div>
</form>
@endsection
