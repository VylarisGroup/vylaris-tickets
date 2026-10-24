@extends('layouts.app')
@section('title', 'Nachricht an Teilnehmende')
@section('subnav') @include('organizer._subnav') @endsection
@section('content')
<a href="{{ route('organizer.events.show', [$organizer, $event]) }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand"><x-icon name="arrow-left" :size="14" />{{ $event->title }}</a>
<h1 class="mb-1 mt-2 text-2xl font-extrabold tracking-tight">Nachricht an Teilnehmende</h1>
<p class="mb-6 text-sm text-slate-500">Geht an alle {{ $recipients }} Personen mit bezahlter Bestellung. Antworten landen bei {{ $organizer->contact_email ?: 'dir' }}.</p>
<form method="POST" action="{{ route('organizer.messages.store', [$organizer, $event]) }}" class="card max-w-2xl space-y-4">@csrf
    <x-field name="subject" label="Betreff" :value="old('subject')" required maxlength="150" placeholder="z. B. Neuer Beginn: Einlass ab 19 Uhr" />
    <div>
        <label class="label" for="body">Nachricht</label>
        <textarea id="body" name="body" rows="8" required maxlength="5000" class="input">{{ old('body') }}</textarea>
        @error('body')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <button class="btn btn-primary" onclick="return confirm('Nachricht jetzt an {{ $recipients }} Empfänger senden?')" @disabled($recipients === 0)><x-icon name="mail" :size="16" />An alle senden</button>
        <button name="test" value="1" class="btn btn-ghost">Test an mich</button>
    </div>
</form>
@endsection
