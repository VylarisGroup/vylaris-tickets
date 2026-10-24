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
    <div class="flex gap-3"><button class="btn btn-primary">Speichern</button><a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Abbrechen</a></div>
</form>

@if ($user->exists)
<section class="card mt-6 max-w-2xl">
    <h2 class="mb-1 font-semibold">Mandanten</h2>
    <p class="mb-4 text-sm text-slate-500">Ein Benutzer kann beliebig vielen Resellern und Veranstaltern angehören und im Profilmenü zwischen ihnen wechseln.</p>
    <div class="divide-y divide-slate-100">
        @foreach ($resellers as $r)
            <div class="flex items-center justify-between gap-3 py-2 text-sm">
                <div><span class="font-medium">{{ $r->name }}</span> <span class="chip">Reseller · {{ \App\Enums\ResellerRole::tryFrom($r->pivot->role)?->label() ?? $r->pivot->role }}</span></div>
                <form method="POST" action="{{ route('admin.users.detach', [$user, 'reseller', $r->id]) }}" onsubmit="return confirm('Zuordnung entfernen?')">@csrf @method('DELETE')<button class="text-red-600">Entfernen</button></form>
            </div>
        @endforeach
        @foreach ($organizers as $o)
            <div class="flex items-center justify-between gap-3 py-2 text-sm">
                <div><span class="font-medium">{{ $o->name }}</span> <span class="chip">Veranstalter · {{ \App\Enums\OrganizerRole::tryFrom($o->pivot->role)?->label() ?? $o->pivot->role }}</span></div>
                <form method="POST" action="{{ route('admin.users.detach', [$user, 'organizer', $o->id]) }}" onsubmit="return confirm('Zuordnung entfernen?')">@csrf @method('DELETE')<button class="text-red-600">Entfernen</button></form>
            </div>
        @endforeach
        @if ($resellers->isEmpty() && $organizers->isEmpty())<div class="py-2 text-sm text-slate-500">Keine Zuordnungen.</div>@endif
    </div>

    <form method="POST" action="{{ route('admin.users.attach', $user) }}" class="mt-5 grid gap-3 border-t border-slate-200/70 pt-4 sm:grid-cols-[1fr_1fr_auto]"
          x-data="{ type: 'organizer' }">@csrf
        <div class="sm:col-span-3 text-sm font-semibold">Mandant zuordnen oder Rolle ändern</div>
        <select name="type" x-model="type" class="input" aria-label="Art"><option value="organizer">Veranstalter</option><option value="reseller">Reseller</option></select>
        <div>
            <select name="tenant_id" class="input" aria-label="Mandant" x-show="type === 'organizer'" :disabled="type !== 'organizer'">@foreach ($allOrganizers as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select>
            <select name="tenant_id" class="input" aria-label="Mandant" x-show="type === 'reseller'" x-cloak :disabled="type !== 'reseller'">@foreach ($allResellers as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>
        </div>
        <div class="sm:col-span-2">
            <select name="role" class="input" aria-label="Rolle" x-show="type === 'organizer'" :disabled="type !== 'organizer'">@foreach (\App\Enums\OrganizerRole::cases() as $c)<option value="{{ $c->value }}">{{ $c->label() }}</option>@endforeach</select>
            <select name="role" class="input" aria-label="Rolle" x-show="type === 'reseller'" x-cloak :disabled="type !== 'reseller'">@foreach (\App\Enums\ResellerRole::cases() as $c)<option value="{{ $c->value }}">{{ $c->label() }}</option>@endforeach</select>
        </div>
        <button class="btn btn-primary">Zuordnen</button>
    </form>
</section>
@endif
@endsection
