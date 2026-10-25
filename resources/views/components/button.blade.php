@props([
    'variant' => 'dark',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconTrailing' => null,
    'type' => 'button',
])

@php
    $classes = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ice disabled:cursor-not-allowed disabled:opacity-45 aria-disabled:pointer-events-none aria-disabled:opacity-45 '
        .match ($size) {
            'sm' => 'h-9 px-4 text-sm',
            'lg' => 'h-13 px-6 text-base',
            default => 'h-11 px-5 text-sm',
        }.' '
        .match ($variant) {
            'ice' => 'bg-ice text-white hover:bg-ice-deep',
            'outline' => 'bg-white text-ink ring-1 ring-line hover:ring-ink-soft',
            'ghost' => 'text-ink-soft hover:bg-ink/5 hover:text-ink',
            'danger' => 'bg-white text-coral ring-1 ring-coral/40 hover:bg-coral-soft',
            'sun' => 'bg-sun text-ink hover:brightness-95',
            'light' => 'bg-white text-ink hover:bg-frost',
            default => 'bg-ink text-white hover:bg-ink-soft',
        };
    $iconClass = $size === 'sm' ? 'size-4' : 'size-[18px]';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon) <flux:icon :icon="$icon" :class="$iconClass" /> @endif
        {{ $slot }}
        @if ($iconTrailing) <flux:icon :icon="$iconTrailing" :class="$iconClass" /> @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon) <flux:icon :icon="$icon" :class="$iconClass" /> @endif
        {{ $slot }}
        @if ($iconTrailing) <flux:icon :icon="$iconTrailing" :class="$iconClass" /> @endif
    </button>
@endif
