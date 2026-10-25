@props([
    'label',
    'name',
    'required' => false,
])

@php
    $id = $attributes->get('id', 'field-'.str($name)->replace('.', '-'));
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="text-sm font-semibold">
        {{ $label }}@if ($required)<span class="ml-0.5 text-coral">*</span>@endif
    </label>
    <select id="{{ $id }}" name="{{ $name }}" @required($required) {{ $attributes->except(['class', 'id'])->class([
        'mt-2 h-12 w-full rounded-xl bg-white px-4 text-sm ring-1 ring-inset outline-none focus:ring-ice disabled:bg-paper',
        'ring-coral/60' => $errors->has($name),
        'ring-line' => ! $errors->has($name),
    ]) }}>
        {{ $slot }}
    </select>
    @error($name)
        <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p>
    @enderror
</div>
