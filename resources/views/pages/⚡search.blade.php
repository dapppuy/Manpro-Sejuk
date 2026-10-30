<?php

use App\Enums\TechnicianBadge;
use App\Models\SearchLog;
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
    public string $service = 'cuci';

    #[Url]
    public int $radius = 5;

    #[Url(as: 'tersedia')]
    public bool $availableToday = false;

    #[Url]
    public string $badge = '';

    #[Url(as: 'urut')]
    public string $sort = TechnicianSearch::SORT_RELEVANT;

    public ?string $notice = null;

    public function mount(): void
    {
        if (! in_array($this->radius, config('sejuk.search_radius_options'), true)) {
            $this->radius = (int) config('sejuk.default_search_radius');
        }

        if ($this->selectedService === null) {
            $this->service = (string) $this->services->first()?->slug;
        }

        $this->notice = session('sejuk.rejected_notice');

        SearchLog::create([
            'user_id' => auth()->id(),
            'service_id' => $this->selectedService?->id,
            'results_count' => $this->technicians->count(),
        ]);
    }

    public function resetFilters(): void
    {
        $this->availableToday = false;
        $this->badge = '';
        $this->radius = (int) config('sejuk.default_search_radius');
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

    #[Computed]
    public function address(): ?string
    {
        return app(CustomerLocation::class)->current(auth()->user())['address'] ?? null;
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
}; ?>

@php
    $serviceName = str($this->selectedService?->name ?? 'AC')->lower();
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10" x-data="{ filters: false }">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4 sm:mb-8">
        <div class="min-w-0">
            <p class="flex items-center gap-1.5 text-sm text-mute">
                <flux:icon.map-pin class="size-4 shrink-0" />
                <span class="truncate">{{ $this->address ?? 'Pusat kota '.config('sejuk.city') }}</span>
                <a href="{{ route('home') }}#lokasi" wire:navigate class="shrink-0 font-semibold text-ice hover:text-ice-deep">Ubah</a>
            </p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Teknisi {{ $serviceName }} di sekitarmu</h1>
        </div>
        <div class="flex items-center gap-2">
            <label class="flex items-center gap-2 text-sm">
                <span class="text-mute">Urutkan</span>
                <select wire:model.live="sort" class="h-10 rounded-full bg-white px-4 font-semibold ring-1 ring-line outline-none">
                    <option value="relevan">Paling relevan</option>
                    <option value="terdekat">Terdekat</option>
                    <option value="rating">Rating tertinggi</option>
                </select>
            </label>
            <button type="button" class="inline-flex h-10 items-center gap-2 rounded-full bg-white px-4 text-sm font-semibold ring-1 ring-line lg:hidden" @click="filters = ! filters" :aria-expanded="filters">
                <flux:icon.sliders-horizontal class="size-4" /> Filter
            </button>
        </div>
    </div>

    @if ($notice)
        <div class="rise mb-6 flex items-center justify-between gap-3 rounded-2xl bg-sun-soft px-5 py-3.5 text-sm ring-1 ring-sun/40" role="status">
            <span>{{ $notice }}</span>
            <button type="button" wire:click="$set('notice', null)" aria-label="Tutup" class="text-ink-soft"><flux:icon.x class="size-4" /></button>
        </div>
    @endif

    <div class="grid gap-8 lg:grid-cols-[272px_1fr]">
        <aside class="h-fit space-y-7 lg:sticky lg:top-24 lg:block" :class="filters ? 'block' : 'hidden'" x-cloak>
            <div class="flex items-center gap-2 font-bold">
                <flux:icon.sliders-horizontal class="size-4" /> Filter
            </div>
            <div>
                <p class="mb-2.5 text-xs font-bold tracking-wider text-mute uppercase">Jenis layanan</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->services as $item)
                        <x-chip wire:key="svc-{{ $item->id }}" :active="$service === $item->slug" wire:click="$set('service', '{{ $item->slug }}')">{{ $item->name }}</x-chip>
                    @endforeach
                </div>
            </div>
            <div>
                <p class="mb-2.5 text-xs font-bold tracking-wider text-mute uppercase">Radius</p>
                <div class="flex flex-wrap gap-2">
                    @foreach (config('sejuk.search_radius_options') as $option)
                        <x-chip wire:key="radius-{{ $option }}" :active="$radius === $option" wire:click="$set('radius', {{ $option }})">{{ $option }} km</x-chip>
                    @endforeach
                </div>
            </div>
            <div>
                <p class="mb-2.5 text-xs font-bold tracking-wider text-mute uppercase">Ketersediaan</p>
                <div class="flex flex-wrap gap-2">
                    <x-chip :active="! $availableToday" wire:click="$set('availableToday', false)">Semua</x-chip>
                    <x-chip :active="$availableToday" wire:click="$set('availableToday', true)">Tersedia hari ini</x-chip>
                </div>
            </div>
            <div>
                <p class="mb-2.5 text-xs font-bold tracking-wider text-mute uppercase">Badge minimal</p>
                <div class="flex flex-wrap gap-2">
                    <x-chip :active="$badge === ''" wire:click="$set('badge', '')">Semua</x-chip>
                    <x-chip :active="$badge === 'identitas'" wire:click="$set('badge', 'identitas')">Terverifikasi</x-chip>
                    <x-chip :active="$badge === 'sertifikat'" wire:click="$set('badge', 'sertifikat')">Bersertifikat</x-chip>
                </div>
            </div>
        </aside>

        <section class="relative min-w-0">
            <p class="mb-4 text-sm text-mute tnum">{{ $this->technicians->count() }} teknisi dalam radius {{ $radius }} km</p>
            <div wire:loading.delay class="absolute inset-0 z-10 bg-paper/50"></div>

            @if ($this->technicians->isEmpty())
                <x-card class="rise flex flex-col items-center px-6 py-16 text-center">
                    <span class="grid size-14 place-items-center rounded-2xl bg-paper">
                        <flux:icon.search-x class="size-6 text-mute" />
                    </span>
                    <h2 class="mt-5 text-xl font-bold">Belum ada teknisi di area ini</h2>
                    <p class="mt-2 max-w-sm text-ink-soft">Tidak ada teknisi {{ $serviceName }} yang cocok dalam radius {{ $radius }} km. Coba perluas radius pencarian.</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-2">
                        @if ($this->widerRadius)
                            <x-button wire:click="$set('radius', {{ $this->widerRadius }})">Perluas radius ke {{ $this->widerRadius }} km</x-button>
                        @endif
                        <x-button variant="secondary" wire:click="resetFilters">Reset filter</x-button>
                    </div>
                </x-card>
            @else
                <ul class="space-y-3">
                    @foreach ($this->technicians as $technician)
                        <li wire:key="tech-{{ $technician->id }}" class="rise">
                            <x-tech-card
                                :technician="$technician"
                                :price="$this->selectedService ? $technician->startingPriceFor($this->selectedService->id) : $technician->startingPrice()"
                                :href="route('technicians.show', ['technician' => $technician, 'layanan' => $service])"
                            />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
