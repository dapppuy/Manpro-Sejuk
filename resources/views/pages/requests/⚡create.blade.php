<?php

use App\Enums\AcCapacity;
use App\Enums\OrderStatus;
use App\Enums\TimeWindow;
use App\Enums\VerificationStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\TechnicianProfile;
use App\Services\Geocoder;
use App\Services\OrderWorkflow;
use App\Support\CustomerLocation;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts::customer'), Title('Minta penawaran')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public TechnicianProfile $technician;

    /**
     * Code of an existing order being re-sent to this technician.
     */
    #[Url(as: 'pesanan'), Locked]
    public ?string $orderCode = null;

    #[Url(as: 'layanan')]
    public string $serviceSlug = '';

    public ?int $service_id = null;

    public string $ac_capacity = '';

    public string $ac_brand = '';

    public string $ac_brand_other = '';

    /** @var list<TemporaryUploadedFile> */
    public array $media = [];

    /** @var list<TemporaryUploadedFile> */
    public array $newMedia = [];

    public string $address = '';

    public string $address_detail = '';

    public ?int $day = null;

    public string $time_window = '';

    public bool $warranty = false;

    public string $notes = '';

    public function mount(TechnicianProfile $technician, CustomerLocation $location): void
    {
        abort_unless($technician->verification_status === VerificationStatus::Approved && $technician->is_active, 404);

        $this->technician = $technician->load(['user', 'services']);

        if ($this->existingOrder !== null) {
            $this->fillFromOrder($this->existingOrder);

            return;
        }

        $this->service_id = $this->offeredServices->firstWhere('slug', $this->serviceSlug)?->id ?? $this->offeredServices->first()?->id;

        $current = $location->current(auth()->user());
        $this->address = $current['address'] ?? '';
        $this->address_detail = $current['detail'] ?? (auth()->user()->primaryAddress?->detail ?? '');
    }

    /**
     * The order being re-sent, when the customer comes back from backup selection or a rejected estimate.
     */
    #[Computed]
    public function existingOrder(): ?Order
    {
        if ($this->orderCode === null) {
            return null;
        }

        $order = Order::with('media')->where('code', $this->orderCode)->first();

        abort_if($order === null || $order->customer_id !== auth()->id(), 404);

        // A stale link (the order moved on or was cancelled) starts a fresh request instead.
        if ($order->status !== OrderStatus::CariCadangan) {
            session()->forget('sejuk.pending_order');
            $this->orderCode = null;

            return null;
        }

        return $order;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Service>
     */
    #[Computed]
    public function offeredServices(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->technician->services;
    }

    /**
     * Dates the customer can pick: today and the next six days.
     *
     * @return Collection<int, string>
     */
    #[Computed]
    public function dayOptions(): Collection
    {
        return collect(range(0, (int) config('sejuk.booking_days_ahead')))->mapWithKeys(fn (int $offset) => [
            $offset => match ($offset) {
                0 => 'Hari ini',
                1 => 'Besok',
                default => now()->addDays($offset)->translatedFormat('D, j M'),
            },
        ]);
    }

    /**
     * Required fields still empty, in the order shown by the form.
     *
     * @return list<string>
     */
    #[Computed]
    public function missingFields(): array
    {
        return array_keys(array_filter([
            'PK AC' => $this->ac_capacity === '',
            'merek AC' => $this->ac_brand === '' || ($this->ac_brand === 'Lainnya' && trim($this->ac_brand_other) === ''),
            'alamat' => trim($this->address) === '',
            'tanggal' => $this->day === null,
            'jam' => $this->time_window === '',
        ]));
    }

    public function updatedNewMedia(): void
    {
        $this->validate([
            'newMedia.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,mp4,mov,webm', 'max:20480'],
        ], attributes: ['newMedia.*' => 'file']);

        $remaining = $this->mediaLimit() - count($this->media);
        $this->media = [...$this->media, ...array_slice($this->newMedia, 0, max(0, $remaining))];
        $this->newMedia = [];
    }

    public function removeMedia(int $index): void
    {
        array_splice($this->media, $index, 1);
    }

    public function mediaLimit(): int
    {
        return (int) config('sejuk.max_order_media') - ($this->existingOrder?->media->count() ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'service_id' => ['required', Rule::in($this->offeredServices->pluck('id')->all())],
            'ac_capacity' => ['required', Rule::enum(AcCapacity::class)],
            'ac_brand' => ['required', Rule::in(config('sejuk.brands'))],
            'ac_brand_other' => ['required_if:ac_brand,Lainnya', 'nullable', 'string', 'max:50'],
            'media' => ['array', 'max:'.$this->mediaLimit()],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,mp4,mov,webm', 'max:20480'],
            'address' => ['required', 'string', 'max:255'],
            'address_detail' => ['nullable', 'string', 'max:255'],
            'day' => ['required', 'integer', 'between:0,'.config('sejuk.booking_days_ahead')],
            'time_window' => ['required', Rule::enum(TimeWindow::class)],
            'warranty' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['ac_brand_other' => 'merek AC', 'day' => 'tanggal', 'address_detail' => 'detail lokasi', 'notes' => 'catatan', 'media' => 'foto/video'];
    }

    public function boot(): void
    {
        $this->withValidator(function (Validator $validator) {
            $validator->after(function (Validator $validator) {
                $window = TimeWindow::tryFrom($this->time_window);

                if ($this->day === 0 && $window !== null && now()->hour >= $window->startHour() + 2) {
                    $validator->errors()->add('time_window', 'Slot waktu ini sudah lewat untuk hari ini. Pilih slot lain.');
                }
            });
        });
    }

    public function submit(OrderWorkflow $workflow, CustomerLocation $location, Geocoder $geocoder): void
    {
        abort_unless($this->technician->is_accepting_orders, 422, 'Teknisi sedang tidak menerima pesanan.');

        $validated = $this->validate();

        $current = $location->current(auth()->user());
        $coordinates = $current !== null && $current['address'] === $validated['address'] && $current['latitude'] !== null
            ? ['latitude' => $current['latitude'], 'longitude' => $current['longitude']]
            : $geocoder->search($validated['address']);

        $details = [
            'service_id' => $validated['service_id'],
            'ac_capacity' => $validated['ac_capacity'],
            'ac_brand' => $validated['ac_brand'],
            'ac_brand_other' => $validated['ac_brand'] === 'Lainnya' ? $validated['ac_brand_other'] : null,
            'address' => $validated['address'],
            'address_detail' => $validated['address_detail'] ?: null,
            'latitude' => $coordinates['latitude'] ?? null,
            'longitude' => $coordinates['longitude'] ?? null,
            'preferred_date' => now()->addDays((int) $validated['day'])->toDateString(),
            'time_window' => $validated['time_window'],
            'warranty' => (bool) $validated['warranty'],
            'notes' => $validated['notes'] ?: null,
        ];

        try {
            if ($this->existingOrder !== null) {
                $order = $workflow->reassign($this->existingOrder, $this->technician, auth()->user(), $details);
                $this->storeExtraMedia($order);
            } else {
                $order = $workflow->submit(auth()->user(), $this->technician, $details, $this->media);
            }
        } catch (InvalidOrderTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $location->set($validated['address'], $details['latitude'], $details['longitude'], detail: $details['address_detail']);
        session()->forget(['sejuk.hidden_technicians', 'sejuk.rejected_notice', 'sejuk.pending_order']);

        $this->redirectRoute('orders.show', $order, navigate: true);
    }

    private function storeExtraMedia(Order $order): void
    {
        foreach ($this->media as $file) {
            $order->media()->create([
                'path' => $file->store("order-media/{$order->id}", 'local'),
                'type' => str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'foto',
            ]);
        }
    }

    private function fillFromOrder(Order $order): void
    {
        $this->service_id = $this->offeredServices->contains('id', $order->service_id) ? $order->service_id : $this->offeredServices->first()?->id;
        $this->ac_capacity = $order->ac_capacity->value;
        $this->ac_brand = $order->ac_brand;
        $this->ac_brand_other = (string) $order->ac_brand_other;
        $this->address = $order->address;
        $this->address_detail = (string) $order->address_detail;
        $offset = (int) now()->startOfDay()->diffInDays($order->preferred_date->startOfDay(), false);
        $this->day = $offset >= 0 && $offset <= (int) config('sejuk.booking_days_ahead') ? $offset : null;
        $this->time_window = $order->time_window->value;
        $this->warranty = $order->warranty;
        $this->notes = (string) $order->notes;
    }
}; ?>

@php
    $technician = $this->technician;
    $missing = $this->missingFields;
    $inputClass = 'h-12 w-full rounded-xl bg-white px-4 text-[15px] ring-1 ring-inset outline-none placeholder:text-mute focus:ring-ice';
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <a href="{{ route('technicians.show', $technician) }}" wire:navigate class="mb-6 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke profil
    </a>

    <div class="grid gap-8 lg:grid-cols-[1fr_360px] lg:gap-10">
        <form wire:submit="submit" class="min-w-0">
            <h1 class="text-3xl font-extrabold tracking-tight">Minta penawaran</h1>
            <p class="mt-2 text-ink-soft">
                Semakin lengkap infonya, semakin akurat estimasi dari teknisi. Kolom bertanda <span class="text-coral">*</span> wajib diisi.
            </p>

            @if ($this->existingOrder)
                <div class="mt-5 flex items-start gap-3 rounded-2xl bg-frost px-5 py-4 text-sm ring-1 ring-ice/20">
                    <flux:icon.rotate-ccw class="mt-0.5 size-4 shrink-0 text-ice" />
                    <p>Formulir pesanan <span class="font-semibold tnum">#{{ $this->existingOrder->code }}</span> sudah terisi. Periksa lagi lalu kirim ke {{ $technician->business_name }}.</p>
                </div>
            @endif

            @unless ($technician->is_accepting_orders)
                <div class="mt-5 flex items-start gap-3 rounded-2xl bg-sun-soft px-5 py-4 text-sm text-sun-ink ring-1 ring-sun/40">
                    <flux:icon.info class="mt-0.5 size-4 shrink-0" />
                    <p>{{ $technician->business_name }} sedang tidak menerima pesanan. <a href="{{ route('search') }}" wire:navigate class="font-semibold underline">Cari teknisi lain</a>.</p>
                </div>
            @endunless

            <div class="mt-8 space-y-8">
                <div>
                    <label for="service" class="mb-2 block text-sm font-semibold">Jenis layanan<span class="ml-0.5 text-coral">*</span></label>
                    <select id="service" wire:model="service_id" class="{{ $inputClass }} ring-line">
                        @foreach ($this->offeredServices as $service)
                            <option wire:key="svc-{{ $service->id }}" value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                    @error('service_id') <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="pk" class="mb-2 block text-sm font-semibold">Kapasitas (PK)<span class="ml-0.5 text-coral">*</span></label>
                        <select id="pk" wire:model.live="ac_capacity" @class([$inputClass, 'ring-line', 'text-mute' => $ac_capacity === ''])>
                            <option value="">Pilih kapasitas</option>
                            @foreach (AcCapacity::cases() as $capacity)
                                <option wire:key="pk-{{ $capacity->value }}" value="{{ $capacity->value }}">{{ $capacity->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="brand" class="mb-2 block text-sm font-semibold">Merek AC<span class="ml-0.5 text-coral">*</span></label>
                        <select id="brand" wire:model.live="ac_brand" @class([$inputClass, 'ring-line', 'text-mute' => $ac_brand === ''])>
                            <option value="">Pilih merek</option>
                            @foreach (config('sejuk.brands') as $brand)
                                <option wire:key="brand-{{ $brand }}" value="{{ $brand }}">{{ $brand }}</option>
                            @endforeach
                        </select>
                        @if ($ac_brand === 'Lainnya')
                            <input wire:model.live.debounce.300ms="ac_brand_other" placeholder="Tulis merek AC" class="{{ $inputClass }} mt-2 ring-line" aria-label="Merek AC lainnya" />
                        @endif
                        @error('ac_brand_other') <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
                    </div>
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-semibold">Foto / video kerusakan <span class="font-normal text-mute">(opsional)</span></legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($this->existingOrder?->media ?? [] as $existing)
                            <div wire:key="existing-{{ $existing->id }}" class="relative size-24 overflow-hidden rounded-xl ring-1 ring-line">
                                @if ($existing->type->value === 'video')
                                    <div class="grid size-full place-items-center bg-ink text-white"><flux:icon.video class="size-6" /></div>
                                @else
                                    <img src="{{ $existing->url() }}" alt="Foto kerusakan" class="size-full object-cover" />
                                @endif
                            </div>
                        @endforeach
                        @foreach ($media as $index => $file)
                            <div wire:key="media-{{ $index }}-{{ $file->getFilename() }}" class="relative size-24 overflow-hidden rounded-xl ring-1 ring-line">
                                @if (str_starts_with((string) $file->getMimeType(), 'video/'))
                                    <div class="grid size-full place-items-center bg-ink text-white"><flux:icon.video class="size-6" /></div>
                                @elseif ($file->isPreviewable())
                                    <img src="{{ $file->temporaryUrl() }}" alt="{{ $file->getClientOriginalName() }}" class="size-full object-cover" />
                                @else
                                    <div class="grid size-full place-items-center bg-frost text-xs text-mute">Foto</div>
                                @endif
                                <button type="button" wire:click="removeMedia({{ $index }})" aria-label="Hapus" class="absolute top-1 right-1 grid size-6 place-items-center rounded-full bg-ink/70 text-white">
                                    <flux:icon.x class="size-3.5" />
                                </button>
                            </div>
                        @endforeach
                        @if (count($media) < $this->mediaLimit())
                            <label class="flex size-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-line bg-white text-xs font-medium text-mute hover:border-ice hover:text-ice">
                                <span wire:loading.remove wire:target="newMedia" class="flex flex-col items-center gap-1"><flux:icon.image-plus class="size-5" /> Tambah</span>
                                <flux:icon.loading wire:loading wire:target="newMedia" class="size-5" />
                                <input type="file" wire:model="newMedia" accept="image/*,video/*" multiple class="sr-only" />
                            </label>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-mute">Maks. {{ config('sejuk.max_order_media') }} file. Foto unit dalam, unit luar, atau video saat AC menyala (maks. 20 MB).</p>
                    @error('newMedia.*') <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
                    @error('media') <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-[1.4fr_1fr]">
                    <div>
                        <label for="address" class="mb-2 block text-sm font-semibold">Alamat<span class="ml-0.5 text-coral">*</span></label>
                        <div class="relative">
                            <flux:icon.map-pin class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-mute" />
                            <input id="address" wire:model.live.debounce.400ms="address" placeholder="Jalan, kelurahan" autocomplete="street-address" class="{{ $inputClass }} ring-line pl-10" />
                        </div>
                    </div>
                    <div>
                        <label for="detail" class="mb-2 block text-sm font-semibold">Detail lokasi</label>
                        <input id="detail" wire:model="address_detail" placeholder="Kos Melati, kamar 12" class="{{ $inputClass }} ring-line" />
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-sm font-semibold">Preferensi waktu<span class="ml-0.5 text-coral">*</span></p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <select aria-label="Tanggal kedatangan" wire:model.live="day" @class([$inputClass, 'ring-line', 'text-mute' => $day === null])>
                            <option value="">Pilih tanggal</option>
                            @foreach ($this->dayOptions as $offset => $label)
                                <option wire:key="day-{{ $offset }}" value="{{ $offset }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <select aria-label="Waktu kedatangan" wire:model.live="time_window" @class([$inputClass, 'text-mute' => $time_window === '', 'ring-coral/60' => $errors->has('time_window'), 'ring-line' => ! $errors->has('time_window')])>
                            <option value="">Pilih waktu</option>
                            @foreach (TimeWindow::cases() as $window)
                                <option wire:key="tw-{{ $window->value }}" value="{{ $window->value }}">{{ $window->label() }} · {{ $window->range() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('time_window') <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
                </div>

                <label class="flex cursor-pointer items-start gap-4 rounded-2xl bg-frost p-5 ring-1 ring-ice/20">
                    <input type="checkbox" wire:model="warranty" class="mt-1 size-4 accent-ice" />
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-ice ring-1 ring-line">
                        <flux:icon.shield-check class="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="flex flex-wrap items-center gap-2 font-bold">
                            Garansi Servis {{ config('sejuk.warranty.days') }} Hari
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs text-ice-deep ring-1 ring-ice/20">+{{ rupiah(config('sejuk.warranty.fee')) }}</span>
                        </span>
                        <span class="mt-1 block text-sm text-ink-soft">Kunjungan ulang tanpa biaya jasa jika masalah yang sama muncul kembali.</span>
                    </span>
                </label>

                <div>
                    <label for="note" class="mb-2 block text-sm font-semibold">Catatan untuk teknisi</label>
                    <textarea id="note" wire:model="notes" rows="3" placeholder="Contoh: AC di lantai 2, unit luar di balkon. Bapak kos ada di rumah sampai jam 12." class="w-full rounded-xl bg-white px-4 py-3 text-[15px] ring-1 ring-inset ring-line outline-none placeholder:text-mute focus:ring-ice"></textarea>
                </div>
            </div>

            <div class="mt-10 flex flex-wrap items-center gap-4 border-t border-line pt-6">
                <x-button type="submit" size="lg" icon="send" :disabled="$missing !== [] || ! $technician->is_accepting_orders" wire:loading.attr="disabled" wire:target="submit,newMedia">
                    Kirim permintaan
                </x-button>
                @if ($missing !== [])
                    <p class="flex items-center gap-1.5 text-sm text-coral">
                        <flux:icon.circle-alert class="size-4 shrink-0" />
                        Lengkapi dulu: {{ implode(', ', $missing) }}
                    </p>
                @endif
            </div>
        </form>

        <aside class="h-fit space-y-4 lg:sticky lg:top-24">
            <x-card class="p-5">
                <p class="text-xs font-semibold tracking-wider text-mute uppercase">Dikirim ke</p>
                <div class="mt-3 flex items-center gap-3">
                    <x-avatar :name="$technician->user->name" :src="$technician->user->avatarUrl()" class="!size-14 rounded-xl" />
                    <div>
                        <p class="font-bold">{{ $technician->business_name }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <x-badge-chip :badge="$technician->badge" />
                            <x-rating :value="$technician->rating_avg" />
                        </div>
                    </div>
                </div>
            </x-card>
            <x-card class="p-5">
                <p class="font-bold">Setelah kamu kirim</p>
                <ol class="mt-4 space-y-4 text-sm">
                    @foreach ([
                        ['Respons maksimal '.config('sejuk.response_minutes').' menit', 'Jika tidak ada respons, pilih teknisi cadangan tanpa mengisi ulang form.'],
                        ['Terima estimasi', 'Lihat kisaran biaya berdasarkan layanan dan detail AC-mu.'],
                        ['Jadwal sesuai pilihanmu', 'Tanggal dan rentang waktu dari form langsung dipakai untuk pesanan.'],
                        ['Setujui & bayar di web', 'Biaya final disetujui di sini, lalu dibayar online. Dana ditahan Sejuk sampai kamu konfirmasi selesai.'],
                    ] as $index => [$title, $text])
                        <li wire:key="after-{{ $index }}" class="flex gap-3">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-frost text-xs font-bold text-ice-deep">{{ $index + 1 }}</span>
                            <span>
                                <span class="block font-semibold">{{ $title }}</span>
                                <span class="text-ink-soft">{{ $text }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-5 rounded-xl bg-leaf-soft px-3 py-2.5 text-xs font-medium text-leaf">Batal gratis selama teknisi belum berangkat.</p>
            </x-card>
        </aside>
    </div>
</div>
