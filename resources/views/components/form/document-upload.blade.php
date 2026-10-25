@props([
    'label',
    'name',
    'hint',
    'accept' => 'image/*',
    'file' => null,
])

@php
    $id = 'document-'.str($name)->replace('.', '-');
    $fileName = $file?->getClientOriginalName();
@endphp

<div>
    <label for="{{ $id }}" @class([
        'flex cursor-pointer items-center gap-4 rounded-2xl border-2 border-dashed p-4 sm:p-5',
        'border-leaf/40 bg-leaf-soft' => $fileName,
        'border-coral/50 bg-coral-soft/40' => ! $fileName && $errors->has($name),
        'border-line bg-paper hover:border-ice' => ! $fileName && ! $errors->has($name),
    ])>
        <span @class(['grid size-12 shrink-0 place-items-center rounded-xl bg-white', 'text-leaf' => $fileName, 'text-ice' => ! $fileName])>
            <span wire:loading.remove wire:target="{{ $name }}">
                @if ($fileName)
                    <flux:icon.circle-check class="size-6" />
                @else
                    <flux:icon.image-plus class="size-6" />
                @endif
            </span>
            <flux:icon.loading wire:loading wire:target="{{ $name }}" class="size-6" />
        </span>
        <span class="min-w-0">
            <span class="block font-bold">{{ $fileName ? $label.' siap diperiksa' : $label }}</span>
            <span class="mt-1 block truncate text-xs text-mute">{{ $fileName ?? $hint }}</span>
        </span>
        <input id="{{ $id }}" type="file" accept="{{ $accept }}" class="sr-only" {{ $attributes }} />
    </label>
    @error($name)
        <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p>
    @enderror
</div>
