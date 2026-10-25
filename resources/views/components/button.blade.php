@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconTrailing' => null,
    'type' => 'button',
])

@php
    $classes = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ice disabled:cursor-not-allowed aria-disabled:pointer-events-none '
        .match ($size) {
            'sm' => 'h-9 px-3.5 text-sm',
            'lg' => 'h-13 px-6 text-base',
            default => 'h-11 px-5 text-[15px]',
        }.' '
        .match ($variant) {
            'dark' => 'bg-ink text-white hover:bg-ink/90 disabled:bg-line disabled:text-mute',
            'secondary' => 'bg-white text-ink ring-1 ring-inset ring-line hover:ring-ink/30 disabled:text-mute',
            'ghost' => 'text-ink-soft hover:bg-ink/5',
            'danger' => 'bg-white text-coral ring-1 ring-inset ring-coral/30 hover:bg-coral-soft',
            'light' => 'bg-white text-ink hover:bg-frost',
            default => 'bg-ice text-white hover:bg-ice-deep disabled:bg-line disabled:text-mute',
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
