@props(['href', 'active' => false])
<a href="{{ $href }}" @if ($active) aria-current="page" @endif class="whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ $active ? 'bg-brand/10 text-brand' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">{{ $slot }}</a>
