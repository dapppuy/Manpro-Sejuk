<?php

use App\Enums\AcCapacity;
use App\Models\CustomerUnit;
use App\Models\UnitIssueReport;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts::customer'), Title('Unit AC saya')] class extends Component {
    use WithFileUploads;

    /**
     * Unit being edited: null = none, 0 = new unit, otherwise the unit id.
     */
    public ?int $formId = null;

    public string $name = '';

    public string $room_label = '';

    public bool $is_kos_unit = false;

    public string $brand = '';

    public string $capacity_pk = '';

    public string $ac_type = 'split';

    public string $serial_number = '';

    public string $notes = '';

    public ?TemporaryUploadedFile $sticker = null;

    /**
     * @return Collection<int, CustomerUnit>
     */
    #[Computed]
    public function units(): Collection
    {
        return auth()->user()->customerUnits()
            ->with(['completedServiceLogs' => fn ($query) => $query->limit(1)])
            ->withCount(['issueReports as open_reports_count' => fn ($query) => $query->where('status', UnitIssueReport::STATUS_WAITING)])
            ->orderBy('name')
            ->get();
    }

    public function newUnit(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->formId = 0;
    }

    public function editUnit(int $id): void
    {
        $unit = $this->ownedUnit($id);

        $this->resetValidation();
        $this->resetForm();
        $this->formId = $unit->id;
        $this->name = $unit->name;
        $this->room_label = (string) $unit->room_label;
        $this->is_kos_unit = $unit->is_kos_unit;
        $this->brand = (string) $unit->brand;
        $this->capacity_pk = (string) $unit->capacity_pk;
        $this->ac_type = (string) $unit->ac_type;
        $this->serial_number = (string) $unit->serial_number;
        $this->notes = (string) $unit->notes;
    }

    public function cancelForm(): void
    {
        $this->resetValidation();
        $this->resetForm();
    }

    public function saveUnit(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'room_label' => ['nullable', 'string', 'max:80'],
            'is_kos_unit' => ['boolean'],
            'brand' => ['nullable', Rule::in(config('sejuk.brands'))],
            'capacity_pk' => ['nullable', Rule::enum(AcCapacity::class)],
            'ac_type' => ['nullable', Rule::in(array_keys(CustomerUnit::TYPES))],
            'serial_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
            'sticker' => ['nullable', 'image', 'max:5120'],
        ], attributes: [
            'name' => 'nama unit', 'room_label' => 'ruangan', 'brand' => 'merek', 'capacity_pk' => 'kapasitas',
            'ac_type' => 'jenis AC', 'serial_number' => 'nomor seri', 'notes' => 'catatan', 'sticker' => 'foto stiker',
        ]);

        $attributes = collect($validated)->except('sticker')->map(fn ($value) => $value === '' ? null : $value)->all();
        $unit = $this->formId ? $this->ownedUnit($this->formId) : new CustomerUnit(['user_id' => auth()->id()]);
        $unit->fill($attributes);

        if ($this->sticker !== null) {
            $previous = $unit->sticker_photo_path;
            $unit->sticker_photo_path = $this->sticker->store('unit-stickers/'.auth()->id(), CustomerUnit::PHOTO_DISK) ?: $previous;

            if ($previous !== null && $previous !== $unit->sticker_photo_path) {
                Storage::disk(CustomerUnit::PHOTO_DISK)->delete($previous);
            }
        }

        $isNew = ! $unit->exists;
        $unit->save();
        $this->resetForm();
        unset($this->units);

        Flux::toast(variant: 'success', text: $isNew ? "{$unit->name} ditambahkan. Cetak stiker QR-nya dari halaman riwayat." : "{$unit->name} disimpan.");
    }

    public function deleteUnit(int $id): void
    {
        $unit = $this->ownedUnit($id);

        if ($unit->sticker_photo_path !== null) {
            Storage::disk(CustomerUnit::PHOTO_DISK)->delete($unit->sticker_photo_path);
        }

        $unit->delete();
        unset($this->units);

        Flux::toast(text: "{$unit->name} dihapus.");
    }

    private function ownedUnit(int $id): CustomerUnit
    {
        return auth()->user()->customerUnits()->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->reset('formId', 'name', 'room_label', 'is_kos_unit', 'brand', 'capacity_pk', 'ac_type', 'serial_number', 'notes', 'sticker');
    }
}; ?>

<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-mute">Riwayat AC</p>
            <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Unit AC saya</h1>
            <p class="mt-2 max-w-xl text-ink-soft">Daftarkan setiap AC, lalu tempel stiker QR-nya. Riwayat servis tersimpan di unit itu, jadi tetap ada meski penghuni atau teknisi berganti.</p>
        </div>
        @if ($formId === null)
            <x-button icon="plus" wire:click="newUnit">Tambah unit AC</x-button>
        @endif
    </div>

    @if ($formId !== null)
        <x-card class="mt-7 p-5 sm:p-6">
            <form wire:submit="saveUnit" class="space-y-5">
                <p class="font-bold">{{ $formId === 0 ? 'Unit AC baru' : 'Ubah unit AC' }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input label="Nama unit" name="name" wire:model="name" placeholder="AC kamar depan" required />
                    <x-form.input label="Ruangan / kamar" name="room_label" wire:model="room_label" placeholder="Kamar A-03" />
                    <x-form.select label="Merek" name="brand" wire:model="brand">
                        <option value="">Belum tahu</option>
                        @foreach (config('sejuk.brands') as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.select label="Kapasitas" name="capacity_pk" wire:model="capacity_pk">
                        <option value="">Belum tahu</option>
                        @foreach (AcCapacity::cases() as $capacity)
                            @continue($capacity === AcCapacity::Unknown)
                            <option value="{{ $capacity->value }}">{{ $capacity->label() }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.select label="Jenis AC" name="ac_type" wire:model="ac_type">
                        <option value="">Belum tahu</option>
                        @foreach (CustomerUnit::TYPES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.input label="Nomor seri (opsional)" name="serial_number" wire:model="serial_number" />
                </div>
                <x-form.textarea label="Catatan (opsional)" name="notes" rows="2" wire:model="notes" placeholder="Contoh: unit outdoor di atap, akses lewat tangga belakang." />

                <div>
                    <p class="text-sm font-semibold">Foto stiker spesifikasi <span class="font-normal text-mute">(opsional)</span></p>
                    <label class="mt-2 flex cursor-pointer items-center gap-3 rounded-xl bg-white px-4 py-3 text-sm ring-1 ring-line hover:ring-ice">
                        <flux:icon.camera class="size-5 text-ice" />
                        <span class="min-w-0 flex-1 truncate">{{ $sticker ? $sticker->getClientOriginalName() : 'Pilih foto stiker di badan unit indoor' }}</span>
                        <flux:icon.loading wire:loading wire:target="sticker" class="size-4 text-ice" />
                        <input type="file" accept="image/*" wire:model="sticker" class="sr-only" />
                    </label>
                    @error('sticker') <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model="is_kos_unit" class="mt-0.5 size-4 accent-ice" />
                    <span><span class="font-semibold">Unit di kos / properti sewaan</span><span class="block text-mute">Penghuni bisa melaporkan masalah lewat QR, lalu kamu yang memutuskan pemesanan teknisi.</span></span>
                </label>

                <div class="flex gap-2 border-t border-line pt-5">
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="saveUnit,sticker">Simpan unit</x-button>
                    <x-button type="button" variant="ghost" wire:click="cancelForm">Batal</x-button>
                </div>
            </form>
        </x-card>
    @endif

    <div class="mt-7 grid gap-4 sm:grid-cols-2">
        @forelse ($this->units as $unit)
            @php
                $lastLog = $unit->completedServiceLogs->first();
                $nextDue = $lastLog?->next_recommended_service_date;
            @endphp
            <x-card wire:key="unit-{{ $unit->id }}" class="flex flex-col p-5">
                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-frost text-ice">
                        <flux:icon.snowflake class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-bold">
                            {{ $unit->name }}
                            @if ($unit->is_kos_unit)
                                <span class="rounded-full bg-sun-soft px-2 py-0.5 text-[11px] font-bold text-sun-ink">Kos</span>
                            @endif
                        </p>
                        <p class="truncate text-sm text-ink-soft">{{ collect([$unit->room_label, $unit->specLine()])->filter()->implode(' · ') ?: 'Detail unit belum diisi' }}</p>
                    </div>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-xl bg-paper p-3">
                        <dt class="text-xs text-mute">Servis terakhir</dt>
                        <dd class="mt-0.5 font-semibold">{{ $lastLog?->service_date->translatedFormat('j M Y') ?? 'Belum ada' }}</dd>
                    </div>
                    <div @class(['rounded-xl p-3', 'bg-sun-soft text-sun-ink' => $nextDue?->isPast(), 'bg-paper' => ! $nextDue?->isPast()])>
                        <dt class="text-xs opacity-75">Servis berikutnya</dt>
                        <dd class="mt-0.5 font-semibold">{{ $nextDue?->translatedFormat('j M Y') ?? '-' }}</dd>
                    </div>
                </dl>

                @if ($unit->open_reports_count > 0)
                    <p class="mt-3 flex items-center gap-2 rounded-xl bg-coral-soft px-3 py-2 text-xs font-semibold text-coral">
                        <flux:icon.triangle-alert class="size-4" /> {{ $unit->open_reports_count }} laporan penghuni menunggu
                    </p>
                @endif

                <div class="mt-auto flex flex-wrap items-center gap-1 pt-4">
                    <x-button size="sm" icon="qr-code" :href="route('units.show', $unit)" wire:navigate>Riwayat & QR</x-button>
                    <x-button size="sm" variant="ghost" icon="pencil" wire:click="editUnit({{ $unit->id }})" aria-label="Ubah {{ $unit->name }}" />
                    <x-button size="sm" variant="ghost" icon="trash" class="!text-coral" wire:click="deleteUnit({{ $unit->id }})" wire:confirm="Hapus {{ $unit->name }} beserta riwayat servisnya?" aria-label="Hapus {{ $unit->name }}" />
                </div>
            </x-card>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center ring-1 ring-line sm:col-span-2">
                <flux:icon.qr-code class="mx-auto size-10 text-ice" />
                <p class="mt-3 font-bold">Belum ada unit AC</p>
                <p class="mt-1 text-sm text-mute">Tambahkan AC pertamamu untuk mulai mencatat riwayat servisnya.</p>
            </div>
        @endforelse
    </div>
</div>
