<?php

use App\Concerns\ProfileValidationRules;
use App\Enums\DocumentType;
use App\Models\Service;
use App\Models\TechnicianProfile;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts::mitra'), Title('Profil Teknisi')] class extends Component {
    use ProfileValidationRules, WithFileUploads;

    public bool $editing = false;

    public string $name = '';

    public string $business_name = '';

    public string $phone = '';

    public string $email = '';

    public string $base_area = '';

    public int $years_experience = 0;

    public int $service_radius_km = 10;

    public string $bio = '';

    /** @var list<int> */
    public array $service_ids = [];

    /** @var list<string> */
    public array $brands = [];

    public string $areas = '';

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public function mount(): void
    {
        $this->fillFromProfile();
    }

    #[Computed]
    public function profile(): TechnicianProfile
    {
        return auth()->user()->technicianProfile->load(['user', 'services', 'brands', 'serviceAreas', 'documents', 'experience']);
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function allServices(): Collection
    {
        return Service::orderBy('sort_order')->get();
    }

    public function edit(): void
    {
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->fillFromProfile();
        $this->editing = false;
    }

    public function save(): void
    {
        $profile = $this->profile;
        $user = $profile->user;
        $this->phone = User::normalizePhone($this->phone);

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'email' => $this->emailRules($user->id),
            'phone' => $this->phoneRules($user->id),
            'business_name' => ['required', 'string', 'max:120'],
            'base_area' => ['required', 'string', 'max:120'],
            'years_experience' => ['required', 'integer', 'min:0', 'max:60'],
            'service_radius_km' => ['required', Rule::in(config('sejuk.technician_radius_options'))],
            'bio' => ['nullable', 'string', 'max:1000'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => [Rule::exists('services', 'id')],
            'brands' => ['array'],
            'brands.*' => [Rule::in(config('sejuk.brands'))],
            'areas' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ], attributes: ['service_ids' => 'layanan', 'areas' => 'area layanan', 'base_area' => 'area utama', 'photo' => 'foto profil']);

        DB::transaction(function () use ($profile, $user, $validated) {
            $user->fill(['name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone']]);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            if ($this->photo !== null) {
                $user->avatar_path = $this->photo->store('avatars', 'public');
            }

            $user->save();

            $profile->update([
                'business_name' => $validated['business_name'],
                'base_area' => $validated['base_area'],
                'years_experience' => $validated['years_experience'],
                'service_radius_km' => $validated['service_radius_km'],
                'bio' => $validated['bio'] ?: null,
            ]);

            $profile->services()->sync($validated['service_ids']);
            $profile->prices()->whereNotIn('service_id', $validated['service_ids'])->delete();

            $profile->brands()->delete();
            $profile->brands()->createMany(array_map(fn (string $brand) => ['brand' => $brand], array_values(array_unique($validated['brands']))));

            $profile->serviceAreas()->delete();
            $areas = collect(explode(',', $validated['areas']))->map(fn (string $area) => trim($area))->filter()->unique();
            $profile->serviceAreas()->createMany($areas->map(fn (string $area) => ['area_name' => $area])->values()->all());
        });

        unset($this->profile);
        $this->photo = null;
        $this->editing = false;

        Flux::toast(variant: 'success', text: 'Profil berhasil disimpan.');
    }

    private function fillFromProfile(): void
    {
        $profile = $this->profile;

        $this->name = $profile->user->name;
        $this->business_name = $profile->business_name;
        $this->phone = (string) $profile->user->phone;
        $this->email = $profile->user->email;
        $this->base_area = (string) $profile->base_area;
        $this->years_experience = $profile->years_experience;
        $this->service_radius_km = $profile->service_radius_km;
        $this->bio = (string) $profile->bio;
        $this->service_ids = $profile->services->pluck('id')->all();
        $this->brands = $profile->brands->pluck('brand')->all();
        $this->areas = $profile->serviceAreas->pluck('area_name')->implode(', ');
    }
}; ?>

@php
    $profile = $this->profile;
    $ktp = $profile->documents->firstWhere('type', DocumentType::Ktp);
    $certificate = $profile->documents->firstWhere('type', DocumentType::Sertifikat);
@endphp

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-mute">Akun teknisi</p>
            <h1 class="mt-1 text-3xl font-extrabold">Profil Teknisi</h1>
        </div>
        @if ($editing)
            <div class="flex gap-2">
                <x-button variant="ghost" wire:click="cancel">Batal</x-button>
                <x-button variant="secondary" icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save,photo">Simpan profil</x-button>
            </div>
        @else
            <x-button variant="secondary" icon="pencil" wire:click="edit">Edit profil</x-button>
        @endif
    </div>

    <div class="mt-7 grid gap-6 lg:grid-cols-[280px_1fr]">
        <div class="space-y-5">
            <x-card class="p-6 text-center">
                @if ($photo && ! $errors->has('photo'))
                    <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau foto" class="mx-auto size-24 rounded-full object-cover" />
                @else
                    <x-avatar :name="$profile->user->name" :src="$profile->user->avatarUrl()" class="mx-auto !size-24 rounded-full !text-2xl" />
                @endif
                @if ($editing)
                    <label class="mt-3 inline-flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-ice hover:text-ice-deep">
                        <flux:icon.camera class="size-3.5" /> Ganti foto
                        <input type="file" accept="image/*" wire:model="photo" class="sr-only" />
                    </label>
                    @error('photo') <p class="mt-1 text-xs text-coral">{{ $message }}</p> @enderror
                @endif
                <h2 class="mt-4 text-xl font-extrabold">{{ $profile->user->name }}</h2>
                <p class="mt-1 text-sm text-mute">{{ $profile->business_name }}</p>
                <div class="mt-4 flex flex-col items-center gap-2">
                    <x-badge-chip :badge="$profile->badge" size="md" />
                    <x-badge-levels :badge="$profile->badge" />
                </div>
                <a href="{{ route('technicians.show', $profile) }}" target="_blank" class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-ice hover:text-ice-deep">
                    Lihat profil publik <flux:icon.external-link class="size-3" />
                </a>
            </x-card>

            <x-card class="p-5">
                <p class="text-xs font-bold tracking-wider text-mute uppercase">Status dokumen</p>
                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm font-semibold"><flux:icon.id-card class="size-4 text-ice" /> KTP</span>
                        <span class="text-xs font-bold {{ $ktp?->status->tone() ?? 'text-mute' }}">{{ $ktp?->status->label() ?? 'Belum ada' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm font-semibold"><flux:icon.award class="size-4 text-ice" /> {{ $certificate ? 'Sertifikat' : 'Pengalaman kerja' }}</span>
                        <span class="text-xs font-bold {{ $certificate?->status->tone() ?? 'text-mute' }}">
                            {{ $certificate?->status->label() ?? ($profile->experience ? $profile->experience->years.' tahun' : 'Belum ada') }}
                        </span>
                    </div>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card class="p-5 sm:p-6">
                <x-section-title icon="user-round" title="Informasi teknisi" description="Data kontak dan identitas usaha." />
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <x-form.input label="Nama lengkap" name="name" wire:model="name" :disabled="! $editing" />
                    <x-form.input label="Nama usaha" name="business_name" wire:model="business_name" :disabled="! $editing" />
                    <x-form.input label="Nomor WhatsApp" name="phone" type="tel" wire:model="phone" :disabled="! $editing" />
                    <x-form.input label="Email" name="email" type="email" wire:model="email" :disabled="! $editing" />
                    <x-form.input label="Area utama" name="base_area" wire:model="base_area" :disabled="! $editing" />
                    <x-form.input label="Pengalaman (tahun)" name="years_experience" type="number" min="0" wire:model="years_experience" :disabled="! $editing" />
                    <x-form.select label="Radius layanan" name="service_radius_km" wire:model="service_radius_km" :disabled="! $editing">
                        @foreach (config('sejuk.technician_radius_options') as $option)
                            <option wire:key="r-{{ $option }}" value="{{ $option }}">{{ $option }} km</option>
                        @endforeach
                    </x-form.select>
                    <x-form.input label="Area layanan (pisahkan dengan koma)" name="areas" wire:model="areas" :disabled="! $editing" />
                </div>
            </x-card>

            <x-card class="p-5 sm:p-6">
                <x-section-title icon="wrench" title="Keahlian dan layanan" description="Informasi ini tampil di profil yang dilihat pelanggan." />

                <p class="mt-6 text-sm font-semibold">Layanan yang ditawarkan</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($this->allServices as $service)
                        @php $selected = in_array($service->id, array_map('intval', $service_ids), true); @endphp
                        @if ($editing)
                            <label wire:key="svc-{{ $service->id }}" @class(['cursor-pointer rounded-full px-3.5 py-2 text-sm font-medium transition', 'bg-ink text-white' => $selected, 'bg-white text-ink-soft ring-1 ring-inset ring-line hover:ring-ink/30' => ! $selected])>
                                <input type="checkbox" value="{{ $service->id }}" wire:model.live="service_ids" class="sr-only" /> {{ $service->name }}
                            </label>
                        @elseif ($selected)
                            <span wire:key="svc-{{ $service->id }}" class="rounded-full bg-frost px-3 py-1.5 text-sm font-semibold text-ice-deep">{{ $service->name }}</span>
                        @endif
                    @endforeach
                </div>
                @error('service_ids') <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
                @if ($editing)
                    <p class="mt-2 text-xs text-mute">Menghapus layanan juga menghapus harga acuannya.</p>
                @endif

                <p class="mt-6 text-sm font-semibold">Merek AC yang dikuasai</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (array_diff(config('sejuk.brands'), ['Lainnya']) as $brand)
                        @php $selected = in_array($brand, $brands, true); @endphp
                        @if ($editing)
                            <label wire:key="brand-{{ $brand }}" @class(['cursor-pointer rounded-full px-3.5 py-2 text-sm font-medium transition', 'bg-ink text-white' => $selected, 'bg-white text-ink-soft ring-1 ring-inset ring-line hover:ring-ink/30' => ! $selected])>
                                <input type="checkbox" value="{{ $brand }}" wire:model.live="brands" class="sr-only" /> {{ $brand }}
                            </label>
                        @elseif ($selected)
                            <span wire:key="brand-{{ $brand }}" class="rounded-full bg-white px-3 py-1.5 text-sm font-medium ring-1 ring-line">{{ $brand }}</span>
                        @endif
                    @endforeach
                    @if (! $editing && $brands === [])
                        <p class="text-sm text-mute">Belum dipilih.</p>
                    @endif
                </div>

                <div class="mt-6">
                    @if ($editing)
                        <x-form.textarea label="Bio" name="bio" rows="4" wire:model="bio" placeholder="Ceritakan keahlian dan pengalamanmu." />
                    @else
                        <p class="text-sm font-semibold">Bio</p>
                        <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $profile->bio ?: 'Belum ada bio. Tambahkan supaya pelanggan lebih mengenalmu.' }}</p>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</div>
