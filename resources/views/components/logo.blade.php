@props([
    'mitra' => false,
    'light' => false,
])

<span {{ $attributes->class('inline-flex items-center gap-2') }}>
    <span @class(['grid size-9 place-items-center rounded-xl', 'bg-ice text-white' => ! $light, 'bg-white/10 text-frost' => $light])>
        <flux:icon.snowflake class="size-5" />
    </span>
    <span @class(['text-lg font-extrabold tracking-tight', 'text-white' => $light, 'text-ink' => ! $light])>
        Sejuk
        @if ($mitra)
            <span @class(['ml-0.5 font-semibold', 'text-frost/70' => $light, 'text-ice' => ! $light])>Mitra</span>
        @endif
    </span>
</span>
