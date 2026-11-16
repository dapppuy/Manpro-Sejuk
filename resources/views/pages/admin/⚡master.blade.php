<?php

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin'), Title('Data master')] class extends Component {
    public ?int $editingServiceId = null;

    public string $serviceName = '';

    public string $serviceDescription = '';

    public ?int $priceMin = null;

    public ?int $priceMax = null;

    public string $newArea = '';

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::orderBy('sort_order')->get();
    }

    /**
     * @return Collection<int, Area>
     */
    #[Computed]
    public function areas(): Collection
    {
        return Area::orderBy('sort_order')->orderBy('name')->get();
    }

    public function editService(?int $serviceId = null): void
    {
        $service = $serviceId === null ? null : Service::findOrFail($serviceId);

        $this->editingServiceId = $service->id ?? 0;
        $this->serviceName = $service->name ?? '';
        $this->serviceDescription = $service->description ?? '';
        $this->priceMin = $service?->price_min;
        $this->priceMax = $service?->price_max;
        $this->resetValidation();
    }

    public function cancelService(): void
    {
        $this->reset(['editingServiceId', 'serviceName', 'serviceDescription', 'priceMin', 'priceMax']);
    }

    public function saveService(): void
    {
        $validated = $this->validate([
            'serviceName' => ['required', 'string', 'max:60', Rule::unique('services', 'name')->ignore($this->editingServiceId ?: null)],
            'serviceDescription' => ['required', 'string', 'max:160'],
            'priceMin' => ['nullable', 'integer', 'min:0', 'max:50000000'],
            'priceMax' => ['nullable', 'integer', 'gte:priceMin', 'max:50000000'],
        ], attributes: ['serviceName' => 'nama layanan', 'serviceDescription' => 'deskripsi', 'priceMin' => 'harga terendah', 'priceMax' => 'harga tertinggi']);

        $attributes = [
            'name' => $validated['serviceName'],
            'description' => $validated['serviceDescription'],
            'price_min' => $validated['priceMin'],
            'price_max' => $validated['priceMax'],
        ];

        $service = $this->editingServiceId
            ? tap(Service::findOrFail($this->editingServiceId))->update($attributes)
            : Service::create([...$attributes, 'slug' => $this->uniqueSlug($validated['serviceName']), 'sort_order' => (int) Service::max('sort_order') + 1]);

        AuditLog::record('service.saved', "Layanan {$service->name} disimpan", $service, ['price_min' => $service->price_min, 'price_max' => $service->price_max]);
        $this->cancelService();
        unset($this->services);

        Flux::toast(variant: 'success', text: 'Layanan disimpan.');
    }

    public function addArea(): void
    {
        $validated = $this->validate(['newArea' => ['required', 'string', 'max:80', Rule::unique('areas', 'name')]], attributes: ['newArea' => 'nama area']);

        $area = Area::create(['name' => $validated['newArea'], 'sort_order' => (int) Area::max('sort_order') + 1]);
        AuditLog::record('area.saved', "Area {$area->name} ditambahkan", $area);

        $this->reset('newArea');
        unset($this->areas);
    }

    public function toggleArea(int $areaId): void
    {
        $area = Area::findOrFail($areaId);
        $area->update(['is_active' => ! $area->is_active]);
        AuditLog::record('area.saved', "Area {$area->name} ".($area->is_active ? 'diaktifkan' : 'dinonaktifkan'), $area);

        unset($this->areas);
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $candidate = $slug;
        $suffix = 2;

        while (Service::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }
}; ?>

<div>
    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Data master</h1>
    <p class="mt-1 text-ink-soft">Layanan dengan rentang harga acuan, dan daftar area layanan di Malang.</p>

    <x-card class="mt-6 p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-section-title icon="wrench" title="Layanan & harga acuan" description="Rentang harga tampil sebagai acuan untuk pelanggan dan teknisi, bukan harga final." />
            @if ($editingServiceId === null)
                <x-button size="sm" icon="plus" wire:click="editService">Tambah layanan</x-button>
            @endif
        </div>

        @if ($editingServiceId !== null)
            <form wire:submit="saveService" class="mt-5 grid gap-4 rounded-2xl bg-paper p-4 sm:grid-cols-2">
                <x-form.input label="Nama layanan" name="serviceName" wire:model="serviceName" required />
                <x-form.input label="Deskripsi singkat" name="serviceDescription" wire:model="serviceDescription" required />
                <x-form.input label="Harga acuan terendah (Rp)" name="priceMin" type="number" min="0" wire:model="priceMin" />
                <x-form.input label="Harga acuan tertinggi (Rp)" name="priceMax" type="number" min="0" wire:model="priceMax" />
                <div class="flex gap-2 sm:col-span-2">
                    <x-button type="submit" size="sm">Simpan</x-button>
                    <x-button type="button" size="sm" variant="ghost" wire:click="cancelService">Batal</x-button>
                </div>
            </form>
        @endif

        <ul class="mt-5 divide-y divide-line">
            @foreach ($this->services as $service)
                <li wire:key="svc-{{ $service->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $service->name }}</p>
                        <p class="text-sm text-mute">{{ $service->description }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold tnum">{{ $service->priceRangeLabel() ?? 'Belum ada harga acuan' }}</span>
                        <button type="button" wire:click="editService({{ $service->id }})" class="text-sm font-semibold text-ice hover:text-ice-deep">Ubah</button>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-card>

    <x-card class="mt-6 p-5 sm:p-6">
        <x-section-title icon="map-pin" title="Area layanan" description="Disarankan ke teknisi saat mengisi area. Area nonaktif tidak disarankan lagi." />
        <form wire:submit="addArea" class="mt-5 flex flex-col gap-2 sm:flex-row">
            <input type="text" wire:model="newArea" placeholder="Nama area, misal Tunggulwulung" class="h-11 min-w-0 flex-1 rounded-full bg-white px-4 text-sm ring-1 ring-line outline-none focus:ring-ice" />
            <x-button type="submit" icon="plus">Tambah area</x-button>
        </form>
        @error('newArea') <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($this->areas as $area)
                <button type="button" wire:key="area-{{ $area->id }}" wire:click="toggleArea({{ $area->id }})" title="{{ $area->is_active ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan' }}"
                    @class(['rounded-full px-3.5 py-1.5 text-sm font-semibold ring-1', 'bg-frost text-ice-deep ring-ice/30' => $area->is_active, 'bg-paper text-mute line-through ring-line' => ! $area->is_active])>
                    {{ $area->name }}
                </button>
            @endforeach
        </div>
    </x-card>
</div>
