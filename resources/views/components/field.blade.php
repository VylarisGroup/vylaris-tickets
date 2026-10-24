@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
           {{ $attributes->except('class') }} class="input">
    @if ($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
