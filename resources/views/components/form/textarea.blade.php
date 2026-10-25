@props([
    'label',
    'name',
    'rows' => 4,
    'required' => false,
])

@php
    $id = $attributes->get('id', 'field-'.str($name)->replace('.', '-'));
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="text-sm font-semibold">
        {{ $label }}@if ($required)<span class="ml-0.5 text-coral">*</span>@endif
    </label>
    <textarea id="{{ $id }}" rows="{{ $rows }}" name="{{ $name }}" @required($required) {{ $attributes->except(['class', 'id'])->class([
        'mt-2 w-full rounded-xl bg-white px-4 py-3 text-sm ring-1 ring-inset outline-none placeholder:text-mute focus:ring-ice disabled:bg-paper disabled:text-ink-soft',
        'ring-coral/60' => $errors->has($name),
        'ring-line' => ! $errors->has($name),
    ]) }}></textarea>
    @error($name)
        <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p>
    @enderror
</div>
