@props([
    'badge',
])

@php
    /** @var \App\Enums\TechnicianBadge $badge */
    $rank = $badge->rank();
@endphp

<div {{ $attributes->class('flex gap-1') }} aria-label="Tingkat {{ $rank }} dari 3">
    @foreach ([1, 2, 3] as $level)
        <span @class(['h-1 w-5 rounded-full', 'bg-ice' => $level <= $rank, 'bg-line' => $level > $rank])></span>
    @endforeach
</div>
