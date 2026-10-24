@extends('layouts.base')
@section('body')
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
        <a href="{{ route('home') }}" class="flex items-center gap-3 font-bold">
            @if ($brand['logo'])
                <img src="{{ Storage::disk('public')->url($brand['logo']) }}" alt="" class="h-8 w-auto">
            @else
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-sm text-white">T</span>
            @endif
            {{ $brand['name'] }}
        </a>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="text-sm text-slate-500 hover:text-brand">{{ auth()->check() ? 'Dashboard' : 'Veranstalter-Login' }}</a>
    </div>
</header>
<main class="mx-auto max-w-4xl px-4 py-8">
    <x-flash />
    @yield('content')
</main>
<footer class="mx-auto max-w-4xl px-4 pb-10 text-center text-xs text-slate-400">
    {{ $brand['name'] }}@if ($brand['support_email']) · <a class="underline" href="mailto:{{ $brand['support_email'] }}">{{ $brand['support_email'] }}</a>@endif
</footer>
@endsection
