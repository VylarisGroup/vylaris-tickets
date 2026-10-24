@props(['href', 'active' => false])
<a href="{{ $href }}" class="whitespace-nowrap border-b-2 px-3 py-3 font-medium {{ $active ? 'border-brand text-brand' : 'border-transparent text-slate-600 hover:text-slate-900' }}">{{ $slot }}</a>
