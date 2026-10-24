@extends('layouts.base')
@section('body')
<header class="app-header border-b border-slate-200/70">
    <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
        <a href="{{ route('home') }}" class="flex items-center gap-3 font-bold tracking-tight">
            @if ($brand['logo'])
                <img src="{{ Storage::disk('public')->url($brand['logo']) }}" alt="" class="h-8 w-auto">
            @else
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-brand text-sm font-bold text-white shadow-lg shadow-brand/30">T</span>
            @endif
            {{ $brand['name'] }}
        </a>
        <div class="flex items-center gap-1 sm:gap-2">
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="nav-link">{{ auth()->check() ? 'Dashboard' : 'Veranstalter-Login' }}</a>
            <x-theme-toggle />
        </div>
    </div>
</header>
<main class="rise mx-auto max-w-4xl px-4 py-8 sm:py-12">
    <x-flash />
    @yield('content')
</main>
<footer class="mx-auto max-w-4xl px-4 pb-10 text-center text-xs text-slate-400">
    {{ $brand['name'] }}@if ($brand['support_email']) · <a class="underline" href="mailto:{{ $brand['support_email'] }}">{{ $brand['support_email'] }}</a>@endif
</footer>
@endsection
