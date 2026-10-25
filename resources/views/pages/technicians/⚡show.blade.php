<?php

use App\Enums\VerificationStatus;
use App\Models\Review;
use App\Models\TechnicianProfile;
use App\Services\TechnicianSearch;
use App\Support\CustomerLocation;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::customer')] class extends Component {
    public TechnicianProfile $technician;

    #[Url(as: 'layanan')]
    public string $service = '';

    public bool $showAllReviews = false;

    public function mount(TechnicianProfile $technician): void
    {
        abort_unless($technician->verification_status === VerificationStatus::Approved && $technician->is_active, 404);

        $this->technician = $technician->load(['user', 'services', 'prices.service', 'serviceAreas', 'brands', 'photos', 'availabilities']);
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
        return $this->technician->reviews()->visible()
            ->with(['customer', 'order.service'])
            ->latest()
            ->limit($this->showAllReviews ? 50 : 3)
            ->get();
    }

    #[Computed]
    public function visibleReviewsCount(): int
    {
        return $this->technician->reviews()->visible()->count();
    }

    public function requestUrl(): string
    {
        return Route::has('requests.create')
            ? route('requests.create', array_filter([
                'technician' => $this->technician,
                'layanan' => $this->service ?: null,
                'pesanan' => session('sejuk.pending_order'),
            ]))
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
    $today = $technician->isAvailableToday();
    $prices = $technician->prices->sortBy(fn ($price) => [$price->service->sort_order, $price->price_min]);
    $slotLabel = $slot
        ? ($slot['date']->isToday() ? 'Hari ini' : ($slot['date']->isTomorrow() ? 'Besok' : $slot['date']->translatedFormat('l, j F'))).', '.str($slot['window']->range())->before('–')
        : 'Belum ada slot 7 hari ke depan';
@endphp

<div class="mx-auto max-w-7xl px-4 pt-6 pb-28 sm:px-6 sm:pt-8 lg:pb-8">
    <a href="{{ route('search', array_filter(['layanan' => $service ?: null])) }}" wire:navigate class="mb-6 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke hasil pencarian
    </a>

    <div class="grid gap-10 lg:grid-cols-[1fr_360px]">
        <div class="min-w-0 space-y-10">
            <header class="flex flex-col gap-6 sm:flex-row sm:items-end">
                <x-avatar :name="$technician->user->name" :src="$technician->user->avatarUrl()" class="!size-32 rounded-3xl !text-4xl sm:!size-44" />
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($technician->isPromotedNow()) <x-promo-label /> @endif
                        <x-badge-chip :badge="$technician->badge" size="md" />
                    </div>
                    <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $technician->business_name }}</h1>
                    <p class="mt-1 text-ink-soft">{{ $technician->user->name }}</p>
                    <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        <x-rating :value="$technician->rating_avg" :count="$technician->reviews_count" />
                        <span class="inline-flex items-center gap-1.5 text-ink-soft tnum"><flux:icon.map-pin class="size-4" /> {{ number_format($this->distanceKm, 1, ',', '.') }} km darimu</span>
                        <span class="inline-flex items-center gap-1.5 text-ink-soft tnum"><flux:icon.briefcase class="size-4" /> {{ $technician->years_experience }} tahun · {{ $technician->jobs_count }} pekerjaan</span>
                        @if ($technician->avg_response_minutes)
                            <span class="inline-flex items-center gap-1.5 text-ink-soft tnum"><flux:icon.message-square-text class="size-4" /> Membalas ±{{ $technician->avg_response_minutes }} menit</span>
                        @endif
                    </div>
                </div>
            </header>

            @if ($technician->bio)
                <p class="max-w-2xl text-lg leading-relaxed text-ink-soft">{{ $technician->bio }}</p>
            @endif

            <section id="harga">
                <h2 class="text-xl font-bold">Daftar harga acuan</h2>
                <p class="mt-1 text-sm text-mute">Harga acuan dari teknisi. Estimasi untuk AC-mu dikirim setelah formulir diisi.</p>
                <x-card class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[480px] text-left text-sm">
                        <thead class="bg-paper text-xs tracking-wider text-mute uppercase">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Layanan</th>
                                <th class="px-5 py-3 font-semibold">Keterangan</th>
                                <th class="px-5 py-3 text-right font-semibold">Kisaran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prices as $price)
                                <tr wire:key="price-{{ $price->id }}" class="border-t border-line">
                                    <td class="px-5 py-3.5 font-semibold">{{ $price->service->name }}</td>
                                    <td class="px-5 py-3.5 text-ink-soft">{{ $price->note }}</td>
                                    <td class="px-5 py-3.5 text-right font-semibold whitespace-nowrap tnum">{{ rupiah($price->price_min) }} – {{ rupiah($price->price_max) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            </section>

            <section class="grid gap-8 sm:grid-cols-2">
                <div>
                    <h2 class="text-xl font-bold">Brand AC yang dikuasai</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($technician->brands as $brand)
                            <span wire:key="brand-{{ $brand->id }}" class="rounded-full bg-white px-3 py-1.5 text-sm font-medium ring-1 ring-line">{{ $brand->brand }}</span>
                        @endforeach
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-bold">Area layanan</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($technician->serviceAreas as $area)
                            <span wire:key="area-{{ $area->id }}" class="rounded-full bg-white px-3 py-1.5 text-sm font-medium ring-1 ring-line">{{ $area->area_name }}</span>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="flex items-start gap-4 rounded-2xl bg-white p-5 ring-1 ring-line">
                <flux:icon.shield-check class="mt-0.5 size-5 shrink-0 text-ice" />
                <div class="flex-1">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="font-bold">Status verifikasi: {{ $technician->badge->label() }}</p>
                        <x-badge-levels :badge="$technician->badge" />
                    </div>
                    <p class="mt-1 text-sm text-ink-soft">{{ $technician->badge->description() }}.</p>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-bold">Galeri hasil kerja</h2>
                @if ($technician->photos->isNotEmpty())
                    <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3">
                        @foreach ($technician->photos as $photo)
                            <img wire:key="photo-{{ $photo->id }}" src="{{ $photo->url() }}" alt="{{ $photo->caption ?? 'Hasil kerja '.$technician->business_name.' '.$loop->iteration }}" loading="lazy" class="aspect-[4/3] w-full rounded-2xl object-cover" />
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 flex items-center gap-3 rounded-2xl bg-white px-5 py-4 text-sm text-mute ring-1 ring-line">
                        <flux:icon.images class="size-5 shrink-0" /> Teknisi belum mengunggah foto hasil kerja.
                    </p>
                @endif
            </section>

            <section id="ulasan">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold">Ulasan pelanggan</h2>
                        <p class="mt-1 text-sm text-mute">Pengalaman pelanggan yang pernah memakai jasa {{ $technician->business_name }}.</p>
                    </div>
                    <div class="flex items-center gap-2 rounded-full bg-white px-3 py-1.5 ring-1 ring-line">
                        <x-rating :value="$technician->rating_avg" />
                        <span class="text-xs text-mute tnum">· {{ $technician->reviews_count }} ulasan</span>
                    </div>
                </div>
                <div class="mt-4 grid gap-3">
                    @forelse ($this->reviews as $review)
                        <x-card wire:key="review-{{ $review->id }}" class="p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$review->customer->name" class="rounded-full" />
                                    <div>
                                        <p class="font-bold">{{ $review->customer->name }}</p>
                                        <p class="text-xs text-mute">
                                            @if ($review->order?->service) {{ $review->order->service->name }} · @endif
                                            {{ $review->created_at?->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                                <x-rating :value="$review->rating" />
                            </div>
                            @if ($review->comment)
                                <p class="mt-4 text-sm leading-relaxed text-ink-soft">{{ $review->comment }}</p>
                            @endif
                        </x-card>
                    @empty
                        <x-card class="p-5 text-sm text-mute">Belum ada ulasan.</x-card>
                    @endforelse
                </div>
                @if ($this->visibleReviewsCount > 3)
                    <x-button variant="secondary" class="mt-4" icon="message-square-text" wire:click="$toggle('showAllReviews')">
                        {{ $showAllReviews ? 'Tampilkan lebih sedikit' : 'Lihat semua ulasan ('.$this->visibleReviewsCount.')' }}
                    </x-button>
                @endif
            </section>
        </div>

        <aside class="h-fit lg:sticky lg:top-24">
            <x-card class="p-6 shadow-xl shadow-ink/5">
                <p class="text-xs text-mute">Kisaran seluruh layanan</p>
                <p class="mt-1 text-2xl font-extrabold tracking-tight tnum">{{ rupiah($technician->prices->min('price_min')) }} – {{ rupiah($technician->prices->max('price_max')) }}</p>
                <p @class(['mt-3 inline-flex items-center gap-1.5 text-sm', 'font-medium text-leaf' => $today, 'text-ink-soft' => ! $today])>
                    <flux:icon.clock class="size-4" /> Jadwal terdekat: {{ $slotLabel }}
                </p>
                <div class="hidden lg:block">
                    <x-button size="lg" :href="$this->requestUrl()" class="mt-6 w-full">Minta penawaran</x-button>
                    <p class="mt-3 text-center text-xs leading-relaxed text-mute">Gratis dan tanpa komitmen. Kamu baru terikat setelah menyetujui estimasi dan jadwal.</p>
                </div>
            </x-card>
        </aside>
    </div>

    {{-- Mobile sticky CTA --}}
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-paper/95 px-4 py-3 backdrop-blur lg:hidden">
        <div class="flex items-center gap-4">
            <div class="min-w-0">
                <p class="text-xs text-mute">Mulai dari</p>
                <p class="font-extrabold tnum">{{ rupiah($technician->startingPrice()) }}</p>
            </div>
            <x-button size="lg" :href="$this->requestUrl()" class="flex-1">Minta penawaran</x-button>
        </div>
    </div>
</div>
