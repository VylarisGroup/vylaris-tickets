@extends('layouts.base')
@section('body')
<div class="mx-auto flex min-h-screen max-w-3xl flex-col items-center justify-center px-4 text-center">
    @if ($brand['logo'])
        <img src="{{ Storage::disk('public')->url($brand['logo']) }}" alt="" class="mb-6 h-16 w-auto">
    @endif
    <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">{{ $brand['name'] }}</h1>
    <p class="mt-4 max-w-xl text-lg text-slate-600">Tickets verkaufen, Abendkasse führen und den Einlass steuern. Alles an einem Ort, für Veranstaltungen vor Ort und digital.</p>
    <div class="mt-8 flex flex-wrap justify-center gap-3">
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary">Zum Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary">Anmelden</a>
            @if ($brand['reseller'] ? $brand['reseller']->allow_registration : config('tickets.allow_registration'))
                <a href="{{ route('register') }}" class="btn btn-ghost">Als Veranstalter registrieren</a>
            @endif
        @endauth
    </div>
    @if ($brand['support_email'])
        <p class="mt-10 text-sm text-slate-500">Support: <a class="underline" href="mailto:{{ $brand['support_email'] }}">{{ $brand['support_email'] }}</a></p>
    @endif
</div>
@endsection
