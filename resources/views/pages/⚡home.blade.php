<?php

use App\Enums\TechnicianBadge;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Services\Geocoder;
use App\Support\CustomerLocation;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::customer'), Title('Teknisi AC terdekat di Malang')] class extends Component {
    public string $address = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public bool $showAddressWarning = false;

    public bool $locationOff = false;

    public function mount(CustomerLocation $location): void
    {
        $current = $location->current(auth()->user());

        if ($current !== null) {
            $this->address = $current['address'];
            $this->latitude = $current['latitude'];
            $this->longitude = $current['longitude'];
        }
    }

    /**
     * Typing a new address invalidates coordinates from an earlier lookup.
     */
    public function updatedAddress(): void
    {
        $this->latitude = null;
        $this->longitude = null;

        if (trim($this->address) !== '') {
            $this->showAddressWarning = false;
            $this->locationOff = false;
        }
    }

    /**
     * Called from the browser after the Geolocation API returns a position.
     */
    public function useCoordinates(float $latitude, float $longitude, Geocoder $geocoder): void
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->address = $geocoder->reverse($latitude, $longitude) ?? 'Lokasi saya ('.number_format($latitude, 4).', '.number_format($longitude, 4).')';
        $this->showAddressWarning = false;
        $this->locationOff = false;
    }

    public function locationDenied(): void
    {
        $this->locationOff = true;
    }

    public function searchService(string $slug, CustomerLocation $location, Geocoder $geocoder): void
    {
        $this->address = trim($this->address);

        if ($this->address === '') {
            $this->showAddressWarning = true;
            $this->dispatch('focus-address');

            return;
        }

        if ($this->latitude === null || $this->longitude === null) {
            $coordinates = $geocoder->search($this->address);
            $this->latitude = $coordinates['latitude'] ?? null;
            $this->longitude = $coordinates['longitude'] ?? null;
        }

        $location->set($this->address, $this->latitude, $this->longitude);

        $this->redirectRoute('search', ['layanan' => $slug], navigate: true);
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
    public function activeTechnicians(): int
    {
        return TechnicianProfile::published()->count();
    }
}; ?>

<div>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-ink text-white">
        <img src="{{ config('sejuk.hero_image') }}" alt="Dua teknisi memeriksa unit AC di atap gedung" class="absolute inset-0 size-full object-cover opacity-40" />
        <div class="absolute inset-0 bg-gradient-to-r from-ink via-ink/85 to-ink/20"></div>

        <div class="relative mx-auto grid max-w-7xl items-end gap-10 px-4 pt-12 pb-14 sm:px-6 sm:pt-16 sm:pb-20 lg:grid-cols-[1.15fr_1fr] lg:gap-12 lg:pt-24 lg:pb-24">
            <div class="pb-2">
                <p class="mb-6 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-frost ring-1 ring-white/15 backdrop-blur">
                    <flux:icon.snowflake class="size-3.5" /> {{ config('sejuk.city') }} · {{ $this->activeTechnicians }} teknisi AC aktif
                </p>
                <h1 class="text-[36px] leading-[1.02] font-extrabold tracking-[-0.035em] sm:text-[44px] lg:text-[68px]">
                    Teknisi AC terdekat.
                    <span class="block text-frost/75">Harga jelas sebelum pintu diketuk.</span>
                </h1>
                <p class="mt-6 max-w-md text-base leading-relaxed text-white/70 sm:text-lg">
                    Bandingkan profil dan harga acuan, dapatkan estimasi dan jadwal pasti, lalu setujui sendiri setiap perubahan biaya.
                </p>
            </div>

            <div id="lokasi" class="scroll-mt-24 rounded-3xl bg-white p-5 text-ink shadow-2xl shadow-black/30 sm:p-6 lg:p-7"
                x-data="{
                    detecting: false,
                    detect() {
                        if (! navigator.geolocation) { $wire.locationDenied(); return; }
                        this.detecting = true;
                        navigator.geolocation.getCurrentPosition(
                            (position) => { $wire.useCoordinates(position.coords.latitude, position.coords.longitude).then(() => this.detecting = false) },
                            () => { this.detecting = false; $wire.locationDenied(); $refs.address.focus() },
                            { enableHighAccuracy: true, timeout: 10000 }
                        );
                    }
                }"
                x-on:focus-address.window="window.scrollTo({ top: 0, behavior: 'smooth' }); $refs.address.focus({ preventScroll: true })"
            >
                <label for="addr" class="text-sm font-semibold">Lokasi servis</label>
                <div @class([
                    'mt-2 flex items-center gap-2 rounded-xl bg-paper px-3 ring-1 ring-inset',
                    'ring-coral/60' => $showAddressWarning || $locationOff,
                    'ring-line focus-within:ring-ice' => ! $showAddressWarning && ! $locationOff,
                ])>
                    <flux:icon.map-pin class="size-4 shrink-0 text-mute" />
                    <input id="addr" x-ref="address" wire:model.live.debounce.400ms="address" placeholder="Ketik alamat atau nama kos" autocomplete="street-address"
                        class="h-12 w-full min-w-0 bg-transparent text-[15px] outline-none placeholder:text-mute" />
                    <button type="button" @click="detect()" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold text-ice hover:bg-frost">
                        <flux:icon.crosshair class="size-3.5" x-bind:class="detecting && 'animate-spin'" />
                        <span x-text="detecting ? 'Mencari…' : 'Lokasi saya'">Lokasi saya</span>
                    </button>
                </div>

                @if ($locationOff)
                    <p class="rise mt-2 flex items-start gap-1.5 text-sm text-coral" role="alert">
                        <flux:icon.circle-alert class="mt-0.5 size-4 shrink-0" />
                        Izin lokasi tidak aktif. Ketik alamatmu secara manual di atas.
                    </p>
                @elseif ($showAddressWarning)
                    <p class="rise mt-2 flex items-start gap-1.5 text-sm text-coral" role="alert">
                        <flux:icon.circle-alert class="mt-0.5 size-4 shrink-0" />
                        Isi alamat dulu supaya kami bisa mencari teknisi di sekitarmu.
                    </p>
                @endif

                <p class="mt-6 mb-3 text-sm font-semibold">Butuh layanan apa?</p>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($this->services as $service)
                        <button type="button" wire:key="service-{{ $service->id }}" wire:click="searchService('{{ $service->slug }}')"
                            class="group flex flex-col items-start rounded-2xl bg-paper p-3.5 text-left ring-1 ring-line transition hover:bg-frost hover:ring-ice/40 sm:p-4">
                            <span class="mb-3 grid size-9 place-items-center rounded-xl bg-white text-ice ring-1 ring-line group-hover:ring-ice/30">
                                <flux:icon :icon="$service->icon()" class="size-[18px]" />
                            </span>
                            <span class="font-bold">{{ $service->name }}</span>
                            <span class="mt-0.5 text-xs leading-snug text-mute">{{ $service->description }}</span>
                        </button>
                    @endforeach
                </div>
                <div wire:loading.flex wire:target="searchService" class="mt-3 items-center gap-2 text-xs font-medium text-mute">
                    <flux:icon.loading class="size-3.5" /> Mencari teknisi di sekitarmu…
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="cara-kerja" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-16 sm:px-6 sm:py-20">
        <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
            <div>
                <x-eyebrow>Cara kerja</x-eyebrow>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight lg:text-4xl">Tidak ada biaya kejutan.</h2>
            </div>
            <p class="max-w-md text-ink-soft">Setiap pesanan melewati empat titik kepastian, dan kamu yang menyetujui di setiap langkah.</p>
        </div>

        @php
            $steps = [
                ['icon' => 'map-pin', 'title' => 'Pilih teknisi', 'text' => 'Lihat jarak, badge, rating, galeri kerja, dan daftar harga acuan.'],
                ['icon' => 'receipt-text', 'title' => 'Terima estimasi', 'text' => 'Teknisi mengirim kisaran biaya dan pilihan jadwal berdasarkan formulirmu.'],
                ['icon' => 'calendar-clock', 'title' => 'Setujui & bayar online', 'text' => 'Setujui biaya final, lalu bayar lewat QRIS, VA, atau e-wallet. Dana ditahan sampai AC beres.'],
                ['icon' => 'camera', 'title' => 'Cek bukti kerja', 'text' => 'Foto sebelum & sesudah. Tandai selesai hanya kalau AC sudah beres.'],
            ];
        @endphp

        <ol class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            @foreach ($steps as $index => $step)
                <li wire:key="step-{{ $index }}" class="relative rounded-2xl bg-white p-6 ring-1 ring-line">
                    <span class="absolute top-6 right-6 text-sm font-bold text-mute/50 tnum">0{{ $index + 1 }}</span>
                    <flux:icon :icon="$step['icon']" class="size-6 text-ice" />
                    <h3 class="mt-6 text-lg font-bold">{{ $step['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-ink-soft">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Trust layers --}}
    <section class="border-y border-line bg-white">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-[1fr_1.4fr]">
            <div>
                <x-eyebrow>Lapisan kepercayaan</x-eyebrow>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight">Badge bertingkat, kamu yang memutuskan.</h2>
                <p class="mt-4 max-w-md text-ink-soft">Banyak teknisi berpengalaman belum bersertifikat. Mereka tetap tampil dengan badge yang sesuai, supaya kamu bisa menimbang sendiri.</p>
            </div>
            <div class="grid gap-3">
                @foreach (array_reverse(TechnicianBadge::cases()) as $badge)
                    <div wire:key="badge-{{ $badge->value }}" class="flex items-center justify-between gap-4 rounded-2xl bg-paper px-4 py-4 ring-1 ring-line sm:px-5">
                        <div class="flex items-center gap-4">
                            <flux:icon.circle-check class="size-5 shrink-0 text-ice" />
                            <div>
                                <x-badge-chip :badge="$badge" size="md" />
                                <p class="mt-1.5 text-sm text-ink-soft">{{ $badge->description() }}</p>
                            </div>
                        </div>
                        <x-badge-levels :badge="$badge" class="shrink-0" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-6 rounded-3xl bg-frost px-6 py-8 sm:px-8 sm:py-10">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight">AC kos lagi rewel?</h2>
                <p class="mt-1 text-ink-soft">Rata-rata teknisi membalas dalam {{ config('sejuk.response_minutes') }} menit.</p>
            </div>
            <x-button variant="dark" size="lg" icon-trailing="arrow-right" wire:click="searchService('perbaikan')">
                Cari teknisi perbaikan
            </x-button>
        </div>
    </section>
</div>
