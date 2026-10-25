@props([
    'name',
    'size' => 'md',
    'src' => null,
])

@php
    $initials = \Illuminate\Support\Str::of($name)->explode(' ')->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->implode('');
    $sizeClass = match ($size) {
        'sm' => 'size-8 text-xs',
        'lg' => 'size-12 text-sm',
        default => 'size-10 text-sm',
    };
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" loading="lazy" {{ $attributes->class([$sizeClass, 'shrink-0 object-cover']) }} />
@else
    <span {{ $attributes->class([$sizeClass, 'grid shrink-0 place-items-center bg-frost font-bold text-ice-deep uppercase']) }} aria-hidden="true">{{ $initials }}</span>
@endif
