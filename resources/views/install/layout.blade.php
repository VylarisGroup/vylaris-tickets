@extends('layouts.base')
@section('title', 'Einrichtung')
@section('body')
@php
    $steps = \App\Http\Controllers\Install\InstallController::STEPS;
    $keys = array_keys($steps);
    $current = array_search($step, $keys, true);
@endphp
<div class="mx-auto flex min-h-screen max-w-2xl flex-col px-4 py-10">
    <div class="mb-6 flex items-center gap-3 text-xl font-bold">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand text-white">T</span>
        {{ $brand['name'] }} <span class="text-sm font-normal text-slate-500">Einrichtung</span>
    </div>

    <ol class="mb-6 flex flex-wrap gap-x-4 gap-y-1 text-xs" aria-label="Fortschritt">
        @foreach ($steps as $key => $label)
            @php($i = array_search($key, $keys, true))
            <li class="{{ $i === $current ? 'font-semibold text-brand' : ($i < $current ? 'text-emerald-700' : 'text-slate-400') }}">
                {{ $i < $current ? '✓' : $i + 1 }} {{ $label }}
            </li>
        @endforeach
    </ol>

    <div class="card space-y-5">
        <x-flash />
        @if ($errors->has('db') || $errors->has('mail'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first('db') ?: $errors->first('mail') }}</div>
        @endif
        @yield('step')
    </div>

    <p class="mt-6 text-center text-xs text-slate-400">Version {{ config('tickets.version') }} · Diese Einrichtung ist nach dem Abschluss gesperrt.</p>
</div>
@endsection
