@extends('layouts.app')
@section('title', 'Veranstalter bearbeiten')
@section('subnav') @include('reseller._subnav') @endsection
@section('content')
<a href="{{ route('reseller.organizers.index', $reseller) }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand"><x-icon name="arrow-left" :size="14" />Veranstalter</a>
<h1 class="mb-6 mt-2 text-2xl font-extrabold tracking-tight">{{ $organizer->name }}</h1>
<form method="POST" action="{{ route('reseller.organizers.update', [$reseller, $organizer]) }}" class="card max-w-2xl space-y-4">@csrf @method('PUT')
    <x-field name="name" label="Name" :value="$organizer->name" required />
    <x-field name="contact_email" label="Kontakt-E-Mail" type="email" :value="$organizer->contact_email" />
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $organizer->is_active)) class="rounded border-slate-300"> Aktiv</label>
    <div class="flex gap-3"><button class="btn btn-primary">Speichern</button><a href="{{ route('reseller.organizers.index', $reseller) }}" class="btn btn-ghost">Abbrechen</a></div>
</form>
@endsection
