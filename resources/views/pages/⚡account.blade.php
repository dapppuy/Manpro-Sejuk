<?php

use App\Concerns\ProfileValidationRules;
use App\Enums\OrderStatus;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\Geocoder;
use App\Support\CustomerLocation;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::customer'), Title('Profil saya')] class extends Component {
    use ProfileValidationRules;

    public bool $editing = false;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $city = '';

    /**
     * Address being edited: null = none, 0 = new address, otherwise the address id.
     */
    public ?int $addressFormId = null;

    public string $label = '';

    public string $address = '';

    public string $detail = '';

    public function mount(): void
    {
        $this->fillProfile();
    }

    #[Computed]
    public function user(): User
    {
        return auth()->user();
    }

    /**
     * @return Collection<int, CustomerAddress>
     */
    #[Computed]
    public function addresses(): Collection
    {
        return $this->user->addresses()->orderByDesc('is_primary')->oldest()->get();
    }

    /**
     * @return array{total: int, completed: int, active: int}
     */
    #[Computed]
    public function summary(): array
    {
        $orders = $this->user->orders()->pluck('status');

        return [
            'total' => $orders->count(),
            'completed' => $orders->filter(fn (OrderStatus $status) => $status === OrderStatus::Selesai)->count(),
            'active' => $orders->filter(fn (OrderStatus $status) => $status->isActive())->count(),
        ];
    }

    public function edit(): void
    {
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->resetValidation();
        $this->fillProfile();
        $this->editing = false;
    }

    public function saveProfile(): void
    {
        $user = $this->user;
        $this->phone = User::normalizePhone($this->phone);

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'phone' => $this->phoneRules($user->id),
            'email' => $this->emailRules($user->id),
            'city' => ['required', 'string', 'max:80'],
        ], attributes: ['city' => 'kota']);

        $user->fill($validated);

        if ($user->isDirty('phone')) {
            $user->phone_verified_at = null;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $this->editing = false;

        Flux::toast(variant: 'success', text: 'Profil disimpan.');
    }

    public function newAddress(): void
    {
        $this->resetValidation();
        $this->reset('label', 'address', 'detail');
        $this->addressFormId = 0;
    }

    public function editAddress(int $id): void
    {
        $address = $this->user->addresses()->findOrFail($id);

        $this->resetValidation();
        $this->label = $address->label;
        $this->address = $address->address;
        $this->detail = (string) $address->detail;
        $this->addressFormId = $address->id;
    }

    public function cancelAddress(): void
    {
        $this->addressFormId = null;
    }

    public function saveAddress(Geocoder $geocoder, CustomerLocation $location): void
    {
        $validated = $this->validate([
            'label' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:255'],
        ], attributes: ['label' => 'nama alamat', 'address' => 'alamat', 'detail' => 'detail lokasi']);

        $existing = $this->addressFormId ? $this->user->addresses()->findOrFail($this->addressFormId) : null;
        $coordinates = $existing !== null && $existing->address === $validated['address']
            ? ['latitude' => $existing->latitude, 'longitude' => $existing->longitude]
            : $geocoder->search($validated['address']);

        $values = [
            'label' => $validated['label'],
            'address' => $validated['address'],
            'detail' => $validated['detail'] ?: null,
            'latitude' => $coordinates['latitude'] ?? null,
            'longitude' => $coordinates['longitude'] ?? null,
        ];

        if ($existing !== null) {
            $existing->update($values);
            $address = $existing;
        } else {
            $address = $this->user->addresses()->create([...$values, 'is_primary' => $this->addresses->isEmpty()]);
        }

        if ($address->is_primary) {
            $this->useForSearch($address, $location);
        }

        $this->addressFormId = null;
        unset($this->addresses);

        Flux::toast(variant: 'success', text: 'Alamat disimpan.');
    }

    public function makePrimary(int $id, CustomerLocation $location): void
    {
        $address = $this->user->addresses()->findOrFail($id);

        DB::transaction(function () use ($address) {
            $this->user->addresses()->update(['is_primary' => false]);
            $address->update(['is_primary' => true]);
        });

        $this->useForSearch($address, $location);
        unset($this->addresses);

        Flux::toast(variant: 'success', text: $address->label.' jadi alamat utama.');
    }

    public function deleteAddress(int $id): void
    {
        $address = $this->user->addresses()->findOrFail($id);
        $wasPrimary = $address->is_primary;
        $address->delete();

        if ($wasPrimary) {
            $this->user->addresses()->oldest()->first()?->update(['is_primary' => true]);
        }

        unset($this->addresses);
    }

    /**
     * The primary address becomes the location used for searching and prefilled in the request form.
     */
    private function useForSearch(CustomerAddress $address, CustomerLocation $location): void
    {
        $location->set($address->address, $address->latitude, $address->longitude, $address->label, $address->detail);
    }

    private function fillProfile(): void
    {
        $this->name = $this->user->name;
        $this->phone = (string) $this->user->phone;
        $this->email = $this->user->email;
        $this->city = (string) ($this->user->city ?? config('sejuk.city'));
    }
}; ?>

@php
    $user = $this->user;
    $summary = $this->summary;
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-mute">Akun pelanggan</p>
            <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Profil saya</h1>
        </div>
        @if ($editing)
            <div class="flex gap-2">
                <x-button variant="ghost" wire:click="cancelEdit">Batal</x-button>
                <x-button variant="secondary" icon="check" wire:click="saveProfile">Simpan profil</x-button>
            </div>
        @else
            <x-button variant="secondary" icon="pencil" wire:click="edit">Edit profil</x-button>
        @endif
    </div>

    <div class="mt-7 grid gap-6 lg:grid-cols-[300px_1fr]">
        <div class="space-y-5">
            <x-card class="p-6 text-center">
                <x-avatar :name="$user->name" :src="$user->avatarUrl()" class="mx-auto !size-24 rounded-full !text-2xl" />
                <h2 class="mt-4 text-xl font-extrabold">{{ $user->name }}</h2>
                <p class="mt-1 text-sm text-mute">{{ $user->city ?? config('sejuk.city') }}</p>
                @if ($user->phone_verified_at)
                    <span class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-leaf-soft px-3 py-1 text-xs font-bold text-leaf">
                        <flux:icon.shield-check class="size-3.5" /> Nomor HP terverifikasi
                    </span>
                @else
                    <span class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-ink/5 px-3 py-1 text-xs font-semibold text-ink-soft">
                        <flux:icon.phone class="size-3.5" /> Nomor HP belum terverifikasi
                    </span>
                @endif
            </x-card>

            <x-card class="p-5">
                <p class="text-xs font-bold tracking-wider text-mute uppercase">Ringkasan pesanan</p>
                <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                    @foreach (['Total' => $summary['total'], 'Selesai' => $summary['completed'], 'Aktif' => $summary['active']] as $label => $value)
                        <div wire:key="sum-{{ $loop->index }}" class="rounded-2xl bg-paper px-2 py-3">
                            <dt class="text-xs text-mute">{{ $label }}</dt>
                            <dd class="mt-1 text-xl font-extrabold tnum">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <x-button variant="secondary" size="sm" class="mt-4 w-full" icon="clipboard-list" :href="route('orders.index')" wire:navigate>Lihat pesanan saya</x-button>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card class="p-5 sm:p-6">
                <x-section-title icon="user-round" title="Informasi pribadi" description="Dipakai teknisi untuk menghubungimu saat pesanan berjalan." />
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <x-form.input label="Nama lengkap" name="name" wire:model="name" :disabled="! $editing" autocomplete="name" />
                    <x-form.input label="Nomor HP (WhatsApp)" name="phone" type="tel" wire:model="phone" :disabled="! $editing" autocomplete="tel" />
                    <x-form.input label="Email" name="email" type="email" wire:model="email" :disabled="! $editing" autocomplete="email" />
                    <x-form.input label="Kota" name="city" wire:model="city" :disabled="! $editing" />
                </div>
                @if ($editing)
                    <p class="mt-4 text-xs text-mute">Mengganti nomor HP akan menghapus status terverifikasi.</p>
                @endif
            </x-card>

            <x-card class="p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <x-section-title icon="map-pin" title="Alamat tersimpan" description="Alamat utama otomatis dipakai saat mencari teknisi dan mengisi form permintaan." />
                    @if ($addressFormId === null)
                        <x-button variant="secondary" size="sm" icon="plus" wire:click="newAddress">Tambah alamat</x-button>
                    @endif
                </div>

                @if ($addressFormId !== null)
                    <form wire:submit="saveAddress" class="mt-6 space-y-4 rounded-2xl bg-paper p-4 sm:p-5">
                        <p class="text-sm font-bold">{{ $addressFormId === 0 ? 'Alamat baru' : 'Ubah alamat' }}</p>
                        <div class="grid gap-4 sm:grid-cols-[180px_1fr]">
                            <x-form.input label="Nama alamat" name="label" wire:model="label" placeholder="Kos Melati" />
                            <x-form.input label="Alamat" name="address" wire:model="address" placeholder="Jl. Kertoraharjo, Ketawanggede" />
                        </div>
                        <x-form.input label="Detail lokasi" name="detail" wire:model="detail" placeholder="Kamar 12, lantai 2" />
                        <div class="flex gap-2">
                            <x-button type="submit" size="sm" wire:loading.attr="disabled" wire:target="saveAddress">Simpan alamat</x-button>
                            <x-button type="button" size="sm" variant="ghost" wire:click="cancelAddress">Batal</x-button>
                        </div>
                    </form>
                @endif

                <div class="mt-6 space-y-3">
                    @forelse ($this->addresses as $saved)
                        <div wire:key="addr-{{ $saved->id }}" @class(['flex flex-wrap items-start gap-4 rounded-2xl p-4 ring-1', 'bg-frost/60 ring-ice/30' => $saved->is_primary, 'bg-white ring-line' => ! $saved->is_primary])>
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-ice ring-1 ring-line">
                                <flux:icon.map-pin class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2 font-bold">
                                    {{ $saved->label }}
                                    @if ($saved->is_primary)
                                        <span class="rounded-full bg-ice px-2 py-0.5 text-[11px] font-bold text-white">Utama</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-sm text-ink-soft">{{ $saved->address }}</p>
                                @if ($saved->detail)
                                    <p class="text-sm text-mute">{{ $saved->detail }}</p>
                                @endif
                                @if ($saved->latitude === null)
                                    <p class="mt-1 text-xs text-sun-ink">Lokasi peta belum ditemukan. Jarak teknisi dihitung dari pusat kota.</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-1">
                                @unless ($saved->is_primary)
                                    <x-button size="sm" variant="ghost" wire:click="makePrimary({{ $saved->id }})">Jadikan utama</x-button>
                                @endunless
                                <x-button size="sm" variant="ghost" icon="pencil" wire:click="editAddress({{ $saved->id }})" aria-label="Ubah {{ $saved->label }}" />
                                <x-button size="sm" variant="ghost" icon="trash" class="!text-coral" wire:click="deleteAddress({{ $saved->id }})" wire:confirm="Hapus alamat {{ $saved->label }}?" aria-label="Hapus {{ $saved->label }}" />
                            </div>
                        </div>
                    @empty
                        <p class="rounded-2xl bg-paper px-4 py-5 text-sm text-mute">Belum ada alamat tersimpan.</p>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
</div>
