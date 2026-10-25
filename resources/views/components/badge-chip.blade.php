@props([
    'badge',
    'size' => 'md',
])

@php
    /** @var \App\Enums\TechnicianBadge $badge */
    $tone = match ($badge) {
        \App\Enums\TechnicianBadge::Sertifikat => 'bg-leaf-soft text-leaf',
        \App\Enums\TechnicianBadge::Identitas => 'bg-frost text-ice-deep',
        \App\Enums\TechnicianBadge::Terdaftar => 'bg-paper text-ink-soft ring-1 ring-line',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full font-semibold whitespace-nowrap',
    $size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs',
    $tone,
]) }}>
    <flux:icon :icon="$badge->icon()" class="{{ $size === 'sm' ? 'size-3' : 'size-3.5' }}" />
    {{ $badge->label() }}
</span>
