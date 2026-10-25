@props([
    'technician',
    'price' => null,
    'href',
    'cta' => 'Lihat profil',
])

@php
    /** @var \App\Models\TechnicianProfile $technician */
    $promoted = $technician->isPromotedNow();
    $today = $technician->isAvailableToday();
    $slot = $technician->nextSlot();
@endphp

<a href="{{ $href }}" wire:navigate {{ $attributes->class([
    'group flex w-full items-center gap-4 rounded-2xl bg-white p-3.5 text-left ring-1 transition hover:shadow-lg hover:shadow-ink/5 sm:gap-5 sm:p-4',
    'ring-sun/70' => $promoted,
    'ring-line hover:ring-ink/20' => ! $promoted,
]) }}>
    <x-avatar :name="$technician->user->name" :src="$technician->user->avatarUrl()" class="!size-20 shrink-0 rounded-xl !text-xl sm:!size-28" />
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            @if ($promoted) <x-promo-label /> @endif
            <x-badge-chip :badge="$technician->badge" />
        </div>
        <h3 class="mt-1.5 truncate text-base font-bold sm:text-lg">{{ $technician->business_name }}</h3>
        <p class="truncate text-sm text-ink-soft">{{ $technician->user->name }} · {{ $technician->years_experience }} th pengalaman</p>
        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm sm:mt-2.5">
            <x-rating :value="$technician->rating_avg" :count="$technician->reviews_count" />
            @if ($technician->distanceKm !== null)
                <span class="inline-flex items-center gap-1 text-ink-soft tnum">
                    <flux:icon.map-pin class="size-3.5" /> {{ number_format($technician->distanceKm, 1, ',', '.') }} km
                </span>
            @endif
            <span @class(['inline-flex items-center gap-1', 'font-medium text-leaf' => $today, 'text-mute' => ! $today])>
                <flux:icon.clock class="size-3.5" />
                @if ($slot)
                    {{ $slot['date']->isToday() ? 'Hari ini' : ($slot['date']->isTomorrow() ? 'Besok' : $slot['date']->translatedFormat('D, j M')) }}, {{ str($slot['window']->range())->before('–') }}
                @else
                    Jadwal penuh
                @endif
            </span>
        </div>
        @if ($price !== null)
            <p class="mt-2 text-sm text-mute sm:hidden">Mulai dari <span class="font-extrabold text-ink tnum">{{ rupiah($price) }}</span></p>
        @endif
    </div>
    <div class="hidden shrink-0 text-right sm:block">
        @if ($price !== null)
            <p class="text-xs text-mute">Mulai dari</p>
            <p class="text-xl font-extrabold tnum">{{ rupiah($price) }}</p>
        @endif
        <span class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-ice transition-all group-hover:gap-2">
            {{ $cta }} <flux:icon.chevron-right class="size-4" />
        </span>
    </div>
</a>
