@extends('layouts.base')
@section('body')
<div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
    <a href="{{ route('home') }}" class="mb-8 flex items-center gap-3 text-xl font-bold">
        @if ($brand['logo'])
            <img src="{{ Storage::disk('public')->url($brand['logo']) }}" alt="" class="h-10 w-auto">
        @else
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand text-white">T</span>
        @endif
        {{ $brand['name'] }}
    </a>
    <div class="card w-full max-w-md">
        <x-flash />
        @yield('content')
    </div>
</div>
@endsection
