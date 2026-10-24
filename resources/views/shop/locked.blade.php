@extends('layouts.public')
@section('title', 'Privates Event')
@push('head')<meta name="robots" content="noindex,nofollow">@endpush
@section('content')
<div class="mx-auto max-w-sm py-10 text-center">
    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand/10 text-brand"><x-icon name="key" :size="26" /></div>
    <h1 class="text-2xl font-extrabold tracking-tight">Privates Event</h1>
    <p class="mt-1 text-sm text-slate-500">Für «{{ $event->title }}» brauchst du einen Zugangsschlüssel.</p>
    <form method="POST" action="{{ route('shop.unlock', [$organizer, $event]) }}" class="card mt-6 space-y-4 text-left">@csrf
        <x-field name="key" label="Zugangsschlüssel" required autofocus autocomplete="off" />
        <button class="btn btn-primary w-full">Freischalten</button>
    </form>
</div>
@endsection
