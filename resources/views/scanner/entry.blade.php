@extends('layouts.base')
@section('title', 'Scanner koppeln')
@section('body')
<main class="mx-auto flex min-h-dvh max-w-sm flex-col justify-center px-5 py-10">
    <div class="mb-6 text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand text-white shadow-lg shadow-brand/30"><x-icon name="scan" :size="28" /></div>
        <h1 class="text-2xl font-extrabold tracking-tight">Scanner koppeln</h1>
        <p class="mt-1 text-sm text-slate-500">Gib den 6-stelligen Code ein, der auf dem PC angezeigt wird.</p>
    </div>
    <form method="POST" action="{{ route('scanner.join') }}" class="card space-y-4">@csrf
        <input name="code" value="{{ old('code') }}" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required
               class="input text-center font-mono text-3xl tracking-[0.3em]" placeholder="000000" aria-label="Code">
        @error('code')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
        <button class="btn btn-primary w-full">Koppeln</button>
    </form>
</main>
@endsection
