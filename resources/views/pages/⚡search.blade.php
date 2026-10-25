<?php

use App\Enums\TechnicianBadge;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Services\TechnicianSearch;
use App\Support\CustomerLocation;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::customer'), Title('Cari teknisi AC')] class extends Component {
    #[Url(as: 'layanan')]
    public string $service = '';

    #[Url]
    public int $radius = 5;

    #[Url(as: 'tersedia')]
    public bool $availableToday = false;

    #[Url]
    public string $badge = '';

    #[Url(as: 'urut')]
    public string $sort = TechnicianSearch::SORT_RELEVANT;

    public function mount(): void
    {
        if (! in_array($this->radius, config('sejuk.search_radius_options'), true)) {
            $this->radius = (int) config('sejuk.default_search_radius');
        }
    }

    public function resetFilters(): void
    {
        $this->reset('radius', 'availableToday', 'badge', 'sort');
        $this->radius = (int) config('sejuk.default_search_radius');
    }

    public function widenRadius(int $radius): void
    {
        $this->radius = $radius;
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::orderBy('sort_order')->get();
    }

    #[Computed]
    public function selectedService(): ?Service
    {
        return $this->services->firstWhere('slug', $this->service);
    }

    /**
     * @return array{address: string, label: string|null, detail: string|null, latitude: float|null, longitude: float|null}|null
     */
    #[Computed]
    public function location(): ?array
    {
        return app(CustomerLocation::class)->current(auth()->user());
    }

    /**
     * Technicians the customer chose to skip (e.g. after rejecting an estimate).
     *
     * @return list<int>
     */
    #[Computed]
    public function hiddenTechnicianIds(): array
    {
        return array_map('intval', (array) session('sejuk.hidden_technicians', []));
    }

    /**
     * @return Collection<int, TechnicianProfile>
     */
    #[Computed]
    public function technicians(): Collection
    {
        [$latitude, $longitude] = app(CustomerLocation::class)->coordinates(auth()->user());

        return app(TechnicianSearch::class)->search(
            latitude: $latitude,
            longitude: $longitude,
            serviceId: $this->selectedService?->id,
            radiusKm: $this->radius,
            availableToday: $this->availableToday,
            minimumBadge: TechnicianBadge::tryFrom($this->badge),
            sort: $this->sort,
            excludeIds: $this->hiddenTechnicianIds,
        );
    }

    /**
     * The next larger radius option to suggest when nothing is found.
     */
    #[Computed]
    public function widerRadius(): ?int
    {
        return collect(config('sejuk.search_radius_options'))->first(fn (int $option) => $option > $this->radius);
    }

    public function requestUrl(TechnicianProfile $technician): string
    {
        return Route::has('requests.create')
            ? route('requests.create', array_filter(['technician' => $technician, 'layanan' => $this->service ?: null]))
            : route('technicians.show', $technician);
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:py-10" x-data="{ filters: false }">
    {{-- Heading --}}
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div class="min-w-0">
            <p class="flex items-center gap-1.5 text-sm font-semibold text-mute">
                <flux:icon.map-pin class="size-4 text-ice" />
                @if ($this->location)
                    <span class="truncate">{{ $this->location['address'] }}</span>
                @else
                    <span>Pusat kota {{ config('sejuk.city') }}</span>
                @endif
                <a href="{{ route('home') }}#lokasi" wire:navigate class="shrink-0 text-ice hover:underline">Ubah</a>
            </p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">
                {{ $this->selectedService?->name ?? 'Semua layanan' }}
                <span class="text-mute">· {{ $this->technicians->count() }} teknisi</span>
            </h1>
        </div>

        <div class="flex items-center gap-2">
            <label for="sort" class="text-sm font-semibold text-mute">Urutkan</label>
            <select id="sort" wire:model.live="sort" class="h-10 rounded-full bg-white pr-9 pl-4 text-sm font-semibold text-ink ring-1 ring-line focus:ring-2 focus:ring-ice focus:outline-none">
                <option value="relevan">Paling relevan</option>
                <option value="terdekat">Terdekat</option>
                <option value="rating">Rating tertinggi</option>
            </select>
            <button type="button" class="inline-flex h-10 items-center gap-2 rounded-full bg-white px-4 text-sm font-semibold ring-1 ring-line lg:hidden" @click="filters = ! filters" :aria-expanded="filters">
                <flux:icon.sliders-horizontal class="size-4" /> Filter
            </button>
        </div>
    </div>

    @if (session('sejuk.rejected_notice'))
        <div class="mt-5 flex items-start gap-3 rounded-2xl bg-sun-soft px-4 py-3 text-sm text-sun-ink ring-1 ring-sun-line/50" role="status">
            <flux:icon.info class="mt-0.5 size-4 shrink-0" />
            <p>{{ session('sejuk.rejected_notice') }}</p>
        </div>
    @endif

    {{-- Service chips --}}
    <div class="no-scrollbar -mx-4 mt-5 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <x-chip :active="$service === ''" wire:click="$set('service', '')">Semua</x-chip>
        @foreach ($this->services as $item)
            <x-chip wire:key="chip-{{ $item->id }}" :active="$service === $item->slug" :icon="$item->icon()" wire:click="$set('service', '{{ $item->slug }}')">
                {{ $item->name }}
            </x-chip>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[260px_1fr]">
        {{-- Filters --}}
        <aside class="lg:block" :class="filters ? 'block' : 'hidden'" x-cloak>
            <div class="space-y-6 rounded-3xl bg-white p-5 ring-1 ring-line lg:sticky lg:top-24">
                <fieldset>
                    <legend class="text-xs font-bold tracking-widest text-mute uppercase">Radius</legend>
                    <div class="mt-3 grid grid-cols-4 gap-2">
                        @foreach (config('sejuk.search_radius_options') as $option)
                            <button type="button" wire:key="radius-{{ $option }}" wire:click="$set('radius', {{ $option }})" @class([
                                'h-10 rounded-xl text-sm font-semibold tnum transition',
                                'bg-ink text-white' => $radius === $option,
                                'bg-paper text-ink-soft ring-1 ring-line hover:ring-ink-soft' => $radius !== $option,
                            ])>{{ $option }} km</button>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="text-xs font-bold tracking-widest text-mute uppercase">Ketersediaan</legend>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <x-chip :active="! $availableToday" class="justify-center" wire:click="$set('availableToday', false)">Semua</x-chip>
                        <x-chip :active="$availableToday" class="justify-center" wire:click="$set('availableToday', true)">Hari ini</x-chip>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="text-xs font-bold tracking-widest text-mute uppercase">Badge minimal</legend>
                    <div class="mt-3 flex flex-col gap-2">
                        @foreach (['' => 'Semua teknisi', 'identitas' => 'Terverifikasi', 'sertifikat' => 'Bersertifikat'] as $value => $label)
                            <label wire:key="badge-{{ $value ?: 'all' }}" class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold ring-1 transition has-checked:bg-frost has-checked:ring-ice/40 {{ $badge === $value ? 'ring-ice/40' : 'ring-line' }}">
                                <input type="radio" wire:model.live="badge" value="{{ $value }}" class="size-4 accent-ice" />
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <button type="button" wire:click="resetFilters" class="w-full text-sm font-semibold text-ice hover:underline">Reset filter</button>
            </div>
        </aside>

        {{-- Results --}}
        <section aria-live="polite" class="relative">
            <div wire:loading.delay class="absolute inset-0 z-10 rounded-3xl bg-paper/60"></div>

            @forelse ($this->technicians as $technician)
                @php
                    $promoted = $technician->isPromotedNow();
                    $price = $this->selectedService ? $technician->startingPriceFor($this->selectedService->id) : $technician->startingPrice();
                    $today = $technician->isAvailableToday();
                    $slot = $today ? null : $technician->nextSlot();
                @endphp
                <article wire:key="tech-{{ $technician->id }}" @class([
                    'rise mb-4 rounded-3xl p-4 sm:p-5',
                    'bg-sun-soft/50 ring-2 ring-sun-line' => $promoted,
                    'bg-white ring-1 ring-line' => ! $promoted,
                ])>
                    <div class="flex gap-4">
                        <a href="{{ route('technicians.show', $technician) }}" wire:navigate class="shrink-0">
                            <x-avatar :name="$technician->user->name" size="lg" class="size-16 sm:size-20" />
                        </a>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($promoted) <x-promo-label /> @endif
                                <x-badge-chip :badge="$technician->badge" size="sm" />
                            </div>
                            <a href="{{ route('technicians.show', $technician) }}" wire:navigate class="mt-1.5 block">
                                <h2 class="truncate text-lg font-bold text-ink hover:text-ice">{{ $technician->business_name }}</h2>
                            </a>
                            <p class="text-sm text-mute">{{ $technician->user->name }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-soft">
                                <x-rating :value="$technician->rating_avg" :count="$technician->reviews_count" />
                                <span class="inline-flex items-center gap-1 tnum"><flux:icon.navigation class="size-3.5 text-mute" /> {{ number_format($technician->distanceKm, 1, ',', '') }} km</span>
                                <span class="inline-flex items-center gap-1 tnum"><flux:icon.briefcase class="size-3.5 text-mute" /> {{ $technician->jobs_count }} pekerjaan</span>
                                @if ($technician->avg_response_minutes)
                                    <span class="inline-flex items-center gap-1 tnum"><flux:icon.timer class="size-3.5 text-mute" /> Balas ±{{ $technician->avg_response_minutes }} mnt</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 border-t border-line/70 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            @if ($today)
                                <x-pill tone="leaf" icon="circle-check">Tersedia hari ini</x-pill>
                            @elseif ($slot)
                                <x-pill tone="mute" icon="calendar-days">{{ $slot['date']->translatedFormat('D, j M') }} · {{ $slot['window']->label() }}</x-pill>
                            @else
                                <x-pill tone="mute">Jadwal penuh minggu ini</x-pill>
                            @endif
                            @if ($price !== null)
                                <p class="text-sm text-mute">Mulai dari <span class="text-base font-extrabold text-ink tnum">{{ rupiah($price) }}</span></p>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <x-button variant="outline" size="sm" :href="route('technicians.show', $technician)" wire:navigate class="flex-1 sm:flex-none">Lihat profil</x-button>
                            <x-button variant="dark" size="sm" :href="$this->requestUrl($technician)" class="flex-1 sm:flex-none">Minta penawaran</x-button>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl bg-white px-6 py-14 text-center ring-1 ring-line">
                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-frost text-ice-deep">
                        <flux:icon.search class="size-6" />
                    </span>
                    <h2 class="mt-4 text-lg font-bold">Belum ada teknisi yang cocok</h2>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-mute">Coba perluas radius atau longgarkan filter supaya lebih banyak teknisi muncul.</p>
                    <div class="mt-6 flex flex-col justify-center gap-2 sm:flex-row">
                        @if ($this->widerRadius)
                            <x-button variant="dark" wire:click="widenRadius({{ $this->widerRadius }})">Perluas radius ke {{ $this->widerRadius }} km</x-button>
                        @endif
                        <x-button variant="outline" wire:click="resetFilters">Reset filter</x-button>
                    </div>
                </div>
            @endforelse
        </section>
    </div>
</div>
