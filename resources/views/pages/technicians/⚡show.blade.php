<?php

use App\Enums\VerificationStatus;
use App\Models\Review;
use App\Models\TechnicianProfile;
use App\Services\TechnicianSearch;
use App\Support\CustomerLocation;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::customer')] class extends Component {
    public TechnicianProfile $technician;

    public int $reviewLimit = 5;

    public function mount(TechnicianProfile $technician): void
    {
        abort_unless($technician->verification_status === VerificationStatus::Approved && $technician->is_active, 404);

        $this->technician = $technician->load(['user', 'services', 'prices.service', 'serviceAreas', 'brands', 'photos', 'availabilities']);
    }

    public function showMoreReviews(): void
    {
        $this->reviewLimit += 5;
    }

    #[Computed]
    public function distanceKm(): float
    {
        [$latitude, $longitude] = app(CustomerLocation::class)->coordinates(auth()->user());

        return TechnicianSearch::distanceKm($latitude, $longitude, (float) $this->technician->latitude, (float) $this->technician->longitude);
    }

    /**
     * @return Collection<int, Review>
     */
    #[Computed]
    public function reviews(): Collection
    {
        return $this->technician->reviews()->visible()->with('customer')->latest()->limit($this->reviewLimit)->get();
    }

    #[Computed]
    public function visibleReviewsCount(): int
    {
        return $this->technician->reviews()->visible()->count();
    }

    public function requestUrl(): string
    {
        return Route::has('requests.create')
            ? route('requests.create', ['technician' => $this->technician])
            : route('login');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return $this->view()->title($this->technician->business_name);
    }
}; ?>

@php
    $technician = $this->technician;
    $slot = $technician->nextSlot();
    $pricesByService = $technician->prices->sortBy(fn ($price) => [$price->service->sort_order, $price->price_min])->groupBy('service_id');
@endphp

<div class="mx-auto max-w-7xl px-4 pt-6 pb-28 sm:px-6 lg:pt-10 lg:pb-10">
    <a href="{{ route('search') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-mute hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke hasil pencarian
    </a>

    <div class="mt-4 grid gap-6 lg:grid-cols-[1fr_340px]">
        <div class="min-w-0 space-y-6">
            {{-- Header --}}
            <x-card class="rise">
                <div class="flex flex-col gap-5 sm:flex-row">
                    <x-avatar :name="$technician->user->name" size="xl" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge-chip :badge="$technician->badge" />
                            @if ($technician->isPromotedNow()) <x-promo-label /> @endif
                        </div>
                        <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $technician->business_name }}</h1>
                        <p class="text-mute">Pemilik: {{ $technician->user->name }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-ink-soft">
                            <x-rating :value="$technician->rating_avg" :count="$technician->reviews_count" />
                            <span class="inline-flex items-center gap-1.5 tnum"><flux:icon.navigation class="size-4 text-mute" /> {{ number_format($this->distanceKm, 1, ',', '') }} km dari lokasimu</span>
                        </div>
                    </div>
                </div>

                <dl class="mt-6 grid grid-cols-3 gap-2 sm:gap-3">
                    <div class="rounded-2xl bg-paper p-3 sm:p-4">
                        <dt class="text-xs text-mute">Pengalaman</dt>
                        <dd class="mt-1 text-lg font-extrabold tnum">{{ $technician->years_experience }} th</dd>
                    </div>
                    <div class="rounded-2xl bg-paper p-3 sm:p-4">
                        <dt class="text-xs text-mute">Pekerjaan</dt>
                        <dd class="mt-1 text-lg font-extrabold tnum">{{ $technician->jobs_count }}</dd>
                    </div>
                    <div class="rounded-2xl bg-paper p-3 sm:p-4">
                        <dt class="text-xs text-mute">Rata-rata balas</dt>
                        <dd class="mt-1 text-lg font-extrabold tnum">{{ $technician->avg_response_minutes ? '±'.$technician->avg_response_minutes.' mnt' : '–' }}</dd>
                    </div>
                </dl>

                @if ($technician->bio)
                    <p class="mt-5 leading-relaxed text-ink-soft">{{ $technician->bio }}</p>
                @endif
            </x-card>

            {{-- Gallery --}}
            <x-card>
                <h2 class="text-lg font-bold">Galeri kerja</h2>
                @if ($technician->photos->isNotEmpty())
                    <div class="no-scrollbar -mx-5 mt-4 flex snap-x gap-3 overflow-x-auto px-5 sm:mx-0 sm:grid sm:grid-cols-3 sm:px-0">
                        @foreach ($technician->photos as $photo)
                            <img wire:key="photo-{{ $photo->id }}" src="{{ $photo->url() }}" alt="{{ $photo->caption ?? 'Hasil kerja '.$technician->business_name }}" loading="lazy"
                                class="aspect-[4/3] w-64 shrink-0 snap-start rounded-2xl object-cover ring-1 ring-line sm:w-full" />
                        @endforeach
                    </div>
                @else
                    <div class="mt-4 flex items-center gap-3 rounded-2xl bg-paper px-4 py-5 text-sm text-mute">
                        <flux:icon.images class="size-5 shrink-0" />
                        Teknisi belum mengunggah foto hasil kerja.
                    </div>
                @endif
            </x-card>

            {{-- Prices --}}
            <x-card id="harga">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-lg font-bold">Harga acuan</h2>
                    <p class="text-xs text-mute">Harga final mengikuti hasil pengecekan, dan selalu kamu setujui dulu.</p>
                </div>
                <div class="mt-4 divide-y divide-line">
                    @foreach ($pricesByService as $prices)
                        <div wire:key="price-group-{{ $prices->first()->service_id }}" class="py-4 first:pt-0 last:pb-0">
                            <p class="flex items-center gap-2 font-semibold text-ink">
                                <flux:icon :icon="$prices->first()->service->icon()" class="size-4 text-ice" />
                                {{ $prices->first()->service->name }}
                            </p>
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($prices as $price)
                                    <li wire:key="price-{{ $price->id }}" class="flex items-baseline justify-between gap-4 text-sm">
                                        <span class="text-ink-soft">{{ $price->note }}</span>
                                        <span class="shrink-0 font-bold text-ink tnum">{{ rupiah_range($price->price_min, $price->price_max) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Brands & areas --}}
            <div class="grid gap-6 sm:grid-cols-2">
                <x-card>
                    <h2 class="text-lg font-bold">Merek yang dikuasai</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($technician->brands as $brand)
                            <x-pill wire:key="brand-{{ $brand->id }}">{{ $brand->brand }}</x-pill>
                        @endforeach
                    </div>
                </x-card>
                <x-card>
                    <h2 class="text-lg font-bold">Area layanan</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($technician->serviceAreas as $area)
                            <x-pill wire:key="area-{{ $area->id }}" tone="ice" icon="map-pin">{{ $area->area_name }}</x-pill>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-mute">Radius layanan hingga {{ $technician->service_radius_km }} km.</p>
                </x-card>
            </div>

            {{-- Reviews --}}
            <x-card>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold">Ulasan pelanggan</h2>
                    <x-rating :value="$technician->rating_avg" :count="$this->visibleReviewsCount" />
                </div>
                <div class="mt-4 space-y-4">
                    @forelse ($this->reviews as $review)
                        <article wire:key="review-{{ $review->id }}" class="rounded-2xl bg-paper p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$review->customer->name" size="sm" />
                                    <div>
                                        <p class="text-sm font-semibold">{{ $review->customer->name }}</p>
                                        <p class="text-xs text-mute">{{ $review->created_at?->translatedFormat('j F Y') }}</p>
                                    </div>
                                </div>
                                <span class="flex gap-0.5" aria-label="Rating {{ $review->rating }} dari 5">
                                    @foreach (range(1, 5) as $star)
                                        <flux:icon.star wire:key="star-{{ $review->id }}-{{ $star }}" @class(['size-4', 'fill-sun text-sun' => $star <= $review->rating, 'text-line' => $star > $review->rating]) />
                                    @endforeach
                                </span>
                            </div>
                            @if ($review->comment)
                                <p class="mt-3 text-sm leading-relaxed text-ink-soft">{{ $review->comment }}</p>
                            @endif
                        </article>
                    @empty
                        <p class="rounded-2xl bg-paper px-4 py-5 text-sm text-mute">Belum ada ulasan.</p>
                    @endforelse
                </div>
                @if ($this->visibleReviewsCount > $reviewLimit)
                    <x-button variant="outline" size="sm" class="mt-4 w-full" wire:click="showMoreReviews">Lihat ulasan lainnya</x-button>
                @endif
            </x-card>
        </div>

        {{-- Sidebar --}}
        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <x-card>
                <p class="text-xs font-bold tracking-widest text-mute uppercase">Jadwal terdekat</p>
                @if ($slot)
                    <p class="mt-2 text-lg font-bold">{{ $slot['date']->isToday() ? 'Hari ini' : $slot['date']->translatedFormat('l, j F') }}</p>
                    <p class="text-sm text-mute">{{ $slot['window']->label() }} · {{ $slot['window']->range() }}</p>
                @else
                    <p class="mt-2 text-sm text-mute">Belum ada slot kosong dalam 7 hari ke depan.</p>
                @endif

                <p class="mt-5 text-xs font-bold tracking-widest text-mute uppercase">Mulai dari</p>
                <p class="mt-1 text-2xl font-extrabold tnum">{{ rupiah($technician->startingPrice()) }}</p>

                <div class="mt-5 hidden lg:block">
                    <x-button variant="dark" size="lg" :href="$this->requestUrl()" class="w-full" icon-trailing="arrow-right">
                        Minta penawaran
                    </x-button>
                    <p class="mt-3 text-center text-xs text-mute">Gratis. Teknisi merespons maksimal {{ config('sejuk.response_minutes') }} menit.</p>
                </div>
            </x-card>

            <div class="rounded-3xl bg-frost/60 p-5 text-sm text-ink-soft ring-1 ring-ice/15">
                <p class="flex items-center gap-2 font-semibold text-ink"><flux:icon.shield-check class="size-4 text-ice" /> Dana ditahan Sejuk</p>
                <p class="mt-1">Pembayaranmu baru diteruskan ke teknisi setelah kamu konfirmasi pekerjaan selesai.</p>
            </div>
        </aside>
    </div>

    {{-- Mobile sticky CTA --}}
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper/95 p-4 backdrop-blur lg:hidden">
        <div class="mx-auto flex max-w-7xl items-center gap-4">
            <div class="min-w-0">
                <p class="text-xs text-mute">Mulai dari</p>
                <p class="text-lg font-extrabold tnum">{{ rupiah($technician->startingPrice()) }}</p>
            </div>
            <x-button variant="dark" size="lg" :href="$this->requestUrl()" class="flex-1">Minta penawaran</x-button>
        </div>
    </div>
</div>
