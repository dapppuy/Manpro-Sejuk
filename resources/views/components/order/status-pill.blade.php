@props([
    'status',
])

@php
    /** @var \App\Enums\OrderStatus $status */
    $tone = match ($status->tone()) {
        'sun' => 'bg-sun-soft text-sun-ink',
        'leaf' => 'bg-leaf-soft text-leaf',
        'coral' => 'bg-coral-soft text-coral',
        default => 'bg-frost text-ice-deep',
    };
@endphp

<span {{ $attributes->class(['rounded-full px-3.5 py-1.5 text-sm font-semibold whitespace-nowrap', $status === \App\Enums\OrderStatus::Dibatalkan ? 'bg-ink/5 text-ink-soft' : $tone]) }}>{{ $status->label() }}</span>
