@extends('layouts.base')
@section('body')
<div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-12">
    <div class="absolute right-4 top-4" style="margin-top: env(safe-area-inset-top)"><x-theme-toggle /></div>
    <a href="{{ route('home') }}" class="rise mb-8 flex items-center gap-3 text-xl font-bold tracking-tight">
        @if ($brand['logo'])
            <img src="{{ Storage::disk('public')->url($brand['logo']) }}" alt="" class="h-10 w-auto">
        @else
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand text-white shadow-xl shadow-brand/40">T</span>
        @endif
        {{ $brand['name'] }}
    </a>
    <div class="card rise w-full max-w-md" style="animation-delay: 80ms">
        <x-flash />
        @yield('content')
    </div>
</div>
@endsection
