@props([
    'tone' => 'mute',
    'icon' => null,
])

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap',
    match ($tone) {
        'ice' => 'bg-frost text-ice-deep',
        'sun' => 'bg-sun-soft text-sun-ink',
        'leaf' => 'bg-leaf-soft text-leaf',
        'coral' => 'bg-coral-soft text-coral',
        'dark' => 'bg-ink text-white',
        default => 'bg-paper text-ink-soft ring-1 ring-line',
    },
]) }}>
    @if ($icon)
        <flux:icon :icon="$icon" class="size-3.5" />
    @endif
    {{ $slot }}
</span>
