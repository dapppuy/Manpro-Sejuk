@props([
    'value' => 0,
    'count' => null,
    'size' => 'md',
])

<span {{ $attributes->class(['inline-flex items-center gap-1 font-semibold text-ink', $size === 'sm' ? 'text-xs' : 'text-sm']) }}>
    <flux:icon.star class="{{ $size === 'sm' ? 'size-3.5' : 'size-4' }} fill-sun text-sun" />
    <span class="tnum">{{ number_format((float) $value, 1, ',', '') }}</span>
    @if ($count !== null)
        <span class="font-medium text-mute tnum">({{ $count }})</span>
    @endif
</span>
