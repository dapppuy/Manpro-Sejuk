@props([
    'mitra' => false,
    'light' => false,
])

<span {{ $attributes->class('inline-flex items-center gap-2') }}>
    <span @class(['grid size-8 place-items-center rounded-xl', 'bg-ink text-frost' => ! $light, 'bg-white/10 text-frost' => $light])>
        <flux:icon.snowflake class="size-[18px]" stroke-width="2.5" />
    </span>
    <span @class(['text-lg font-extrabold tracking-tight', 'text-white' => $light])>{{ $mitra ? 'Sejuk Mitra' : 'Sejuk' }}</span>
</span>
