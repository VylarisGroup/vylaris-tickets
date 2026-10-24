@extends('layouts.app')
@section('title', 'Veranstalter bearbeiten')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<a href="{{ route('admin.organizers.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand"><x-icon name="arrow-left" :size="14" />Veranstalter</a>
<h1 class="mb-6 mt-2 text-2xl font-extrabold tracking-tight">{{ $organizer->name }}</h1>
<form method="POST" action="{{ route('admin.organizers.update', $organizer) }}" class="card max-w-2xl space-y-4">@csrf @method('PUT')
    <x-field name="name" label="Name" :value="$organizer->name" required />
    <x-field name="contact_email" label="Kontakt-E-Mail" type="email" :value="$organizer->contact_email" />
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label" for="reseller_id">Reseller</label>
            <select id="reseller_id" name="reseller_id" class="input">
                <option value="">Direkt (kein Reseller)</option>
                @foreach ($resellers as $r)<option value="{{ $r->id }}" @selected((string) old('reseller_id', $organizer->reseller_id) === (string) $r->id)>{{ $r->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="default_currency">Währung</label>
            <select id="default_currency" name="default_currency" class="input">@foreach (config('tickets.currencies') as $c)<option @selected(old('default_currency', $organizer->default_currency) === $c)>{{ $c }}</option>@endforeach</select>
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $organizer->is_active)) class="rounded border-slate-300"> Aktiv (gesperrte Veranstalter sind nicht erreichbar)</label>
    <div class="flex gap-3"><button class="btn btn-primary">Speichern</button><a href="{{ route('admin.organizers.index') }}" class="btn btn-ghost">Abbrechen</a></div>
</form>
@endsection
