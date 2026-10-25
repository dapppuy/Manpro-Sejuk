<?php

use App\Models\Service;
use App\Models\TechnicianPrice;
use App\Models\TechnicianProfile;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::mitra'), Title('Harga acuan')] class extends Component {
    /**
     * Editable rows keyed by price id.
     *
     * @var array<int, array{note: string, price_min: int|string, price_max: int|string}>
     */
    public array $rows = [];

    /**
     * New-row drafts keyed by service id.
     *
     * @var array<int, array{note: string, price_min: int|string, price_max: int|string}>
     */
    public array $drafts = [];

    public function mount(): void
    {
        $this->loadRows();
    }

    #[Computed]
    public function profile(): TechnicianProfile
    {
        return auth()->user()->technicianProfile;
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return $this->profile->services()->get();
    }

    /**
     * @return Collection<int, TechnicianPrice>
     */
    #[Computed]
    public function prices(): Collection
    {
        return $this->profile->prices()->orderBy('price_min')->get();
    }

    public function saveRow(int $priceId): void
    {
        $price = $this->profile->prices()->findOrFail($priceId);

        $validated = $this->validate([
            "rows.$priceId.note" => ['required', 'string', 'max:80'],
            "rows.$priceId.price_min" => ['required', 'integer', 'min:5000', 'max:50000000'],
            "rows.$priceId.price_max" => ['required', 'integer', "gte:rows.$priceId.price_min", 'max:50000000'],
        ], attributes: $this->attributesFor("rows.$priceId"));

        $price->update($validated['rows'][$priceId]);

        Flux::toast(variant: 'success', text: 'Harga disimpan.');
    }

    public function addRow(int $serviceId): void
    {
        abort_unless($this->services->contains('id', $serviceId), 403);

        $validated = $this->validate([
            "drafts.$serviceId.note" => ['required', 'string', 'max:80'],
            "drafts.$serviceId.price_min" => ['required', 'integer', 'min:5000', 'max:50000000'],
            "drafts.$serviceId.price_max" => ['required', 'integer', "gte:drafts.$serviceId.price_min", 'max:50000000'],
        ], attributes: $this->attributesFor("drafts.$serviceId"));

        $this->profile->prices()->create(['service_id' => $serviceId, ...$validated['drafts'][$serviceId]]);

        unset($this->prices);
        $this->loadRows();

        Flux::toast(variant: 'success', text: 'Baris harga ditambahkan.');
    }

    public function deleteRow(int $priceId): void
    {
        $this->profile->prices()->whereKey($priceId)->delete();

        unset($this->prices);
        $this->loadRows();
    }

    private function loadRows(): void
    {
        $this->rows = $this->prices
            ->mapWithKeys(fn (TechnicianPrice $price) => [$price->id => ['note' => $price->note, 'price_min' => $price->price_min, 'price_max' => $price->price_max]])
            ->all();

        $this->drafts = $this->services
            ->mapWithKeys(fn (Service $service) => [$service->id => ['note' => '', 'price_min' => '', 'price_max' => '']])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function attributesFor(string $prefix): array
    {
        return ["$prefix.note" => 'keterangan', "$prefix.price_min" => 'harga minimum', "$prefix.price_max" => 'harga maksimum'];
    }
}; ?>

<div>
    <p class="text-sm text-mute">Akun teknisi</p>
    <h1 class="mt-1 text-3xl font-extrabold">Harga acuan</h1>
    <p class="mt-2 max-w-2xl text-ink-soft">Kisaran harga ini tampil di profilmu dan dipakai sebagai isian awal saat mengirim estimasi. "Mulai dari" di hasil pencarian diambil dari harga minimum terendah.</p>

    @if ($this->services->isEmpty())
        <x-card class="mt-7 p-6 text-sm text-mute">
            Belum ada layanan yang kamu tawarkan. <a href="{{ route('mitra.profile') }}" wire:navigate class="font-semibold text-ice">Pilih layanan di profil</a> dulu.
        </x-card>
    @endif

    <div class="mt-7 space-y-6">
        @foreach ($this->services as $service)
            @php $servicePrices = $this->prices->where('service_id', $service->id); @endphp
            <x-card wire:key="service-{{ $service->id }}" class="p-5 sm:p-6">
                <x-section-title :icon="$service->icon()" :title="$service->name" :description="$servicePrices->isEmpty() ? 'Belum ada harga. Tambahkan minimal satu baris.' : $servicePrices->count().' baris harga'" />

                <div class="mt-5 space-y-3">
                    @foreach ($servicePrices as $price)
                        <form wire:key="price-{{ $price->id }}" wire:submit="saveRow({{ $price->id }})" class="grid gap-3 rounded-2xl bg-paper p-4 sm:grid-cols-[1fr_150px_150px_auto] sm:items-start">
                            <x-form.input label="Keterangan" name="rows.{{ $price->id }}.note" wire:model="rows.{{ $price->id }}.note" placeholder="0,5–1 PK" />
                            <x-form.input label="Minimum" name="rows.{{ $price->id }}.price_min" type="number" prefix="Rp" wire:model="rows.{{ $price->id }}.price_min" />
                            <x-form.input label="Maksimum" name="rows.{{ $price->id }}.price_max" type="number" prefix="Rp" wire:model="rows.{{ $price->id }}.price_max" />
                            <div class="flex gap-2 sm:pt-7">
                                <x-button type="submit" variant="secondary" size="sm" class="!h-12">Simpan</x-button>
                                <button type="button" wire:click="deleteRow({{ $price->id }})" wire:confirm="Hapus baris harga ini?" class="grid size-12 place-items-center rounded-xl text-coral hover:bg-coral-soft" aria-label="Hapus baris harga">
                                    <flux:icon.trash class="size-4" />
                                </button>
                            </div>
                        </form>
                    @endforeach

                    <form wire:submit="addRow({{ $service->id }})" class="grid gap-3 rounded-2xl border-2 border-dashed border-line p-4 sm:grid-cols-[1fr_150px_150px_auto] sm:items-start">
                        <x-form.input label="Keterangan baru" name="drafts.{{ $service->id }}.note" wire:model="drafts.{{ $service->id }}.note" placeholder="Misal: 1,5–2 PK" />
                        <x-form.input label="Minimum" name="drafts.{{ $service->id }}.price_min" type="number" prefix="Rp" wire:model="drafts.{{ $service->id }}.price_min" />
                        <x-form.input label="Maksimum" name="drafts.{{ $service->id }}.price_max" type="number" prefix="Rp" wire:model="drafts.{{ $service->id }}.price_max" />
                        <div class="sm:pt-7">
                            <x-button type="submit" variant="secondary" size="sm" icon="plus" class="!h-12 w-full">Tambah</x-button>
                        </div>
                    </form>
                </div>
            </x-card>
        @endforeach
    </div>
</div>
