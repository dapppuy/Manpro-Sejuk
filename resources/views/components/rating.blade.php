@props([
    'value' => 0,
    'count' => null,
])

<span {{ $attributes->class('inline-flex items-center gap-1 text-sm') }}>
    <flux:icon.star class="size-3.5 fill-sun text-sun" />
    <span class="font-semibold tnum">{{ number_format((float) $value, 1, ',', '') }}</span>
    @if ($count !== null)
        <span class="text-mute tnum">({{ $count }} ulasan)</span>
    @endif
</span>
