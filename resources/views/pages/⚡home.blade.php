<?php

use App\Enums\TechnicianBadge;
use App\Models\Service;
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

    public ?string $locationError = null;

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
        $this->showAddressWarning = false;
        $this->locationError = null;
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
        $this->locationError = null;
    }

    public function locationDenied(): void
    {
        $this->locationError = 'Izin lokasi tidak aktif. Ketik alamatmu secara manual, misalnya "Jl. MT Haryono, Lowokwaru".';
    }

    public function searchService(?string $slug, CustomerLocation $location, Geocoder $geocoder): void
    {
        $this->address = trim($this->address);

        if ($this->address === '') {
            $this->showAddressWarning = true;

            return;
        }

        if ($this->latitude === null || $this->longitude === null) {
            $coordinates = $geocoder->search($this->address);
            $this->latitude = $coordinates['latitude'] ?? null;
            $this->longitude = $coordinates['longitude'] ?? null;
        }

        $location->set($this->address, $this->latitude, $this->longitude);

        $this->redirectRoute('search', array_filter(['layanan' => $slug]), navigate: true);
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::orderBy('sort_order')->get();
    }
}; ?>

<div>
    {{-- Hero --}}
    <section class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 sm:pt-6">
        <div class="relative overflow-hidden rounded-[2rem] bg-ink px-5 py-10 text-white sm:px-10 sm:py-14 lg:px-14 lg:py-16">
            <div class="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full bg-ice/40 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 left-1/3 size-72 rounded-full bg-frost/10 blur-3xl"></div>

            <div class="relative grid gap-10 lg:grid-cols-[1.1fr_1fr] lg:items-center">
                <div class="rise">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-frost">
                        <flux:icon.map-pin class="size-3.5" />
                        Khusus {{ config('sejuk.city') }} dan sekitarnya
                    </span>
                    <h1 class="mt-5 text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
                        Teknisi AC terdekat.<br>
                        <span class="text-frost">Harga jelas sebelum pintu diketuk.</span>
                    </h1>
                    <p class="mt-5 max-w-lg text-base text-white/70 sm:text-lg">
                        Bandingkan teknisi terverifikasi di sekitarmu, setujui estimasi, dan bayar online. Dana ditahan Sejuk sampai AC benar-benar beres.
                    </p>
                    <ul class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm text-white/80">
                        <li class="flex items-center gap-1.5"><flux:icon.circle-check class="size-4 text-frost" /> Tanpa biaya kejutan</li>
                        <li class="flex items-center gap-1.5"><flux:icon.circle-check class="size-4 text-frost" /> Batal gratis sebelum berangkat</li>
                        <li class="flex items-center gap-1.5"><flux:icon.circle-check class="size-4 text-frost" /> Tanpa tunai</li>
                    </ul>
                </div>

                {{-- Location card --}}
                <div id="lokasi" class="rise scroll-mt-24 rounded-3xl bg-white p-5 text-ink shadow-2xl shadow-ink/30 sm:p-6"
                    x-data="{
                        locating: false,
                        locate() {
                            if (! navigator.geolocation) { $wire.locationDenied(); return; }
                            this.locating = true;
                            navigator.geolocation.getCurrentPosition(
                                (position) => { $wire.useCoordinates(position.coords.latitude, position.coords.longitude).then(() => this.locating = false) },
                                () => { this.locating = false; $wire.locationDenied() },
                                { enableHighAccuracy: true, timeout: 10000 }
                            );
                        }
                    }"
                >
                    <p class="text-xs font-bold tracking-widest text-mute uppercase">Lokasi servis</p>
                    <form wire:submit="searchService(null)" class="mt-3 space-y-3">
                        <label for="address" class="sr-only">Alamat servis</label>
                        <div @class([
                            'flex items-center gap-2 rounded-2xl bg-paper px-4 ring-1 transition focus-within:ring-2 focus-within:ring-ice',
                            'ring-sun-line' => $showAddressWarning,
                            'ring-line' => ! $showAddressWarning,
                        ])>
                            <flux:icon.map-pin class="size-5 shrink-0 text-ice" />
                            <input id="address" type="text" wire:model.live.debounce.500ms="address" placeholder="Ketik alamat, misal Jl. MT Haryono 12" autocomplete="street-address"
                                class="h-13 w-full bg-transparent text-base font-medium text-ink placeholder:text-mute focus:outline-none" />
                        </div>

                        <button type="button" @click="locate()" :disabled="locating" class="inline-flex items-center gap-2 text-sm font-semibold text-ice hover:text-ice-deep disabled:opacity-60">
                            <flux:icon.locate-fixed class="size-4" x-bind:class="locating && 'animate-spin'" />
                            <span x-text="locating ? 'Mencari lokasimu…' : 'Lokasi saya'">Lokasi saya</span>
                        </button>

                        @if ($locationError)
                            <p class="flex gap-2 rounded-2xl bg-coral-soft px-4 py-3 text-sm text-coral" role="alert">
                                <flux:icon.circle-alert class="mt-0.5 size-4 shrink-0" />
                                {{ $locationError }}
                            </p>
                        @endif

                        @if ($showAddressWarning)
                            <p class="flex gap-2 rounded-2xl bg-sun-soft px-4 py-3 text-sm font-medium text-sun-ink" role="alert">
                                <flux:icon.triangle-alert class="mt-0.5 size-4 shrink-0" />
                                Isi alamat dulu supaya kami bisa mencarikan teknisi terdekat.
                            </p>
                        @endif

                        <x-button type="submit" variant="dark" size="lg" class="w-full" icon-trailing="arrow-right">
                            <span wire:loading.remove wire:target="searchService">Cari teknisi terdekat</span>
                            <span wire:loading wire:target="searchService">Mencari…</span>
                        </x-button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    {{-- Services --}}
    <section class="mx-auto max-w-7xl px-4 pt-14 sm:px-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Butuh servis apa?</h2>
                <p class="mt-1 text-mute">Pilih layanan, kami tampilkan teknisi yang menawarkannya di dekatmu.</p>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            @foreach ($this->services as $service)
                <button type="button" wire:key="service-{{ $service->id }}" wire:click="searchService('{{ $service->slug }}')"
                    class="group rise flex flex-col items-start rounded-3xl bg-white p-4 text-left ring-1 ring-line transition hover:-translate-y-0.5 hover:ring-ice sm:p-6">
                    <span class="grid size-11 place-items-center rounded-2xl bg-frost text-ice-deep transition group-hover:bg-ice group-hover:text-white sm:size-12">
                        <flux:icon :icon="$service->icon()" class="size-5 sm:size-6" />
                    </span>
                    <span class="mt-4 text-base font-bold text-ink sm:text-lg">{{ $service->name }}</span>
                    <span class="mt-1 text-xs text-mute sm:text-sm">{{ $service->description }}</span>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-ice">
                        Cari teknisi <flux:icon.arrow-right class="size-4 transition group-hover:translate-x-0.5" />
                    </span>
                </button>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section id="cara-kerja" class="mx-auto max-w-7xl scroll-mt-20 px-4 pt-16 sm:px-6">
        <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Cara kerja</h2>
        <p class="mt-1 text-mute">Empat langkah, tanpa tawar-menawar di depan pintu.</p>

        @php
            $steps = [
                ['icon' => 'search', 'title' => 'Cari & bandingkan', 'text' => 'Lihat teknisi terdekat lengkap dengan badge, rating, dan harga acuan.'],
                ['icon' => 'file-text', 'title' => 'Minta penawaran', 'text' => 'Isi detail AC sekali. Teknisi merespons maksimal 15 menit dengan estimasi biaya.'],
                ['icon' => 'receipt', 'title' => 'Setujui biaya final', 'text' => 'Setelah dicek di lokasi, setiap biaya tambahan harus kamu setujui dulu.'],
                ['icon' => 'shield-check', 'title' => 'Bayar, dana ditahan', 'text' => 'Bayar online. Dana baru diteruskan ke teknisi setelah kamu konfirmasi AC beres.'],
            ];
        @endphp

        <ol class="mt-6 grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
            @foreach ($steps as $index => $step)
                <li wire:key="step-{{ $index }}" class="rounded-3xl bg-white p-5 ring-1 ring-line sm:p-6">
                    <div class="flex items-center justify-between">
                        <span class="grid size-11 place-items-center rounded-2xl bg-ink text-white">
                            <flux:icon :icon="$step['icon']" class="size-5" />
                        </span>
                        <span class="text-3xl font-extrabold text-line tnum">0{{ $index + 1 }}</span>
                    </div>
                    <h3 class="mt-4 font-bold text-ink">{{ $step['title'] }}</h3>
                    <p class="mt-1 text-sm text-mute">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Trust layers --}}
    <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6">
        <div class="rounded-[2rem] bg-frost/60 p-5 ring-1 ring-ice/15 sm:p-10">
            <div class="max-w-2xl">
                <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Lapisan kepercayaan</h2>
                <p class="mt-1 text-ink-soft">Setiap teknisi punya badge bertingkat yang ditentukan tim Sejuk setelah memeriksa dokumennya.</p>
            </div>
            <div class="mt-6 grid gap-3 sm:grid-cols-3 sm:gap-4">
                @foreach (TechnicianBadge::cases() as $badge)
                    <div wire:key="badge-{{ $badge->value }}" class="rounded-3xl bg-white p-5 ring-1 ring-line">
                        <div class="flex items-center justify-between">
                            <x-badge-chip :badge="$badge" />
                            <span class="text-xs font-bold text-mute">Tingkat {{ $badge->rank() }}</span>
                        </div>
                        <p class="mt-4 text-sm text-ink-soft">{{ $badge->description() }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6">
        <div class="flex flex-col items-start justify-between gap-6 rounded-[2rem] bg-ink p-6 text-white sm:p-10 md:flex-row md:items-center">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">AC tidak dingin atau bocor?</h2>
                <p class="mt-1 text-white/70">Teknisi perbaikan terdekat bisa datang hari ini juga.</p>
            </div>
            <x-button variant="light" size="lg" icon-trailing="arrow-right" wire:click="searchService('perbaikan')">
                Cari teknisi perbaikan
            </x-button>
        </div>
        @if ($showAddressWarning)
            <p class="mt-3 text-sm font-medium text-sun-ink">
                <a href="#lokasi" class="underline">Isi alamat dulu</a> supaya kami bisa mencarikan teknisi terdekat.
            </p>
        @endif
    </section>
</div>
