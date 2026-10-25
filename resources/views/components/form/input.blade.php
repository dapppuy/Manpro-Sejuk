@props([
    'label',
    'name',
    'type' => 'text',
    'prefix' => null,
    'required' => false,
    'hint' => null,
])

@php
    $id = $attributes->get('id', 'field-'.str($name)->replace('.', '-'));
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="text-sm font-semibold">
        {{ $label }}@if ($required)<span class="ml-0.5 text-coral">*</span>@endif
    </label>
    <div class="relative mt-2">
        @if ($prefix)
            <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-sm text-mute">{{ $prefix }}</span>
        @endif
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" @required($required) {{ $attributes->except(['class', 'id'])->class([
            'h-12 w-full rounded-xl bg-white px-4 text-sm ring-1 ring-inset outline-none placeholder:text-mute focus:ring-ice disabled:bg-paper disabled:text-ink-soft',
            'pl-10' => $prefix,
            'ring-coral/60' => $errors->has($name),
            'ring-line' => ! $errors->has($name),
        ]) }} />
    </div>
    @if ($hint)
        <p class="mt-1.5 text-xs text-mute">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p>
    @enderror
</div>
