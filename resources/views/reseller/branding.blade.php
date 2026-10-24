@extends('layouts.app')
@section('title', 'Branding')
@section('subnav') @include('reseller._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">Branding</h1>
<form method="POST" action="{{ route('reseller.branding.update', $reseller) }}" enctype="multipart/form-data" class="card max-w-2xl space-y-4">@csrf @method('PUT')
    <x-field name="brand_name" label="Anzeigename" :value="$reseller->brand_name" hint="Erscheint statt «Vylaris Tickets» auf deiner Domain." />
    <x-field name="brand_color" label="Markenfarbe" :value="$reseller->brand_color" placeholder="#1867F8" hint="Hex-Wert, z. B. #1867F8." />
    <x-field name="support_email" label="Support-E-Mail" type="email" :value="$reseller->support_email" />
    <div>
        <label class="label" for="logo">Logo</label>
        @if ($reseller->logo_path)
            <div class="mb-2 flex items-center gap-4">
                <img src="{{ Storage::disk('public')->url($reseller->logo_path) }}" alt="" class="h-12">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300"> Logo entfernen</label>
            </div>
        @endif
        <input id="logo" type="file" name="logo" accept="image/*" class="block text-sm">
        @error('logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_registration" value="1" @checked(old('allow_registration', $reseller->allow_registration)) class="rounded border-slate-300"> Registrierung neuer Veranstalter erlauben</label>
    <button class="btn btn-primary">Speichern</button>
</form>
@endsection
