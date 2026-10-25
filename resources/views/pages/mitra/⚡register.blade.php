<?php

use App\Actions\Technician\RegisterTechnician;
use App\Concerns\ProfileValidationRules;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts::customer'), Title('Daftar Sejuk Mitra')] class extends Component {
    use ProfileValidationRules, WithFileUploads;

    public string $name = '';

    public string $business_name = '';

    public string $phone = '';

    public string $email = '';

    public string $password = '';

    public string $area = '';

    public ?int $service_id = null;

    public int $radius = 10;

    /** @var TemporaryUploadedFile|null */
    public $ktp = null;

    public string $competency = 'certificate';

    /** @var TemporaryUploadedFile|null */
    public $certificate = null;

    public ?int $experience_years = null;

    public string $reference_phone = '';

    public string $experience_description = '';

    public bool $agreed = false;

    public function mount(): void
    {
        $this->service_id = $this->services->first()?->id;
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::orderBy('sort_order')->get();
    }

    /**
     * What still blocks submission, mirroring the disabled submit button.
     */
    #[Computed]
    public function canSubmit(): bool
    {
        $competencyReady = $this->competency === 'certificate'
            ? $this->certificate !== null
            : $this->experience_years !== null && $this->reference_phone !== '' && $this->experience_description !== '';

        return $this->ktp !== null && $this->agreed && $competencyReady;
    }

    public function updatedKtp(): void
    {
        $this->validateOnly('ktp');
    }

    public function updatedCertificate(): void
    {
        $this->validateOnly('certificate');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $usesCertificate = $this->competency === 'certificate';

        return [
            'name' => $this->nameRules(),
            'business_name' => ['required', 'string', 'max:120'],
            'phone' => $this->phoneRules(),
            'email' => $this->emailRules(),
            'password' => ['required', 'string', Password::default()],
            'area' => ['required', 'string', 'max:120'],
            'service_id' => ['required', Rule::exists('services', 'id')],
            'radius' => ['required', Rule::in(config('sejuk.technician_radius_options'))],
            'ktp' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'competency' => ['required', Rule::in(['certificate', 'experience'])],
            'certificate' => [Rule::requiredIf($usesCertificate), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'experience_years' => [Rule::requiredIf(! $usesCertificate), 'nullable', 'integer', 'min:0', 'max:60'],
            'reference_phone' => [Rule::requiredIf(! $usesCertificate), 'nullable', 'string', 'max:20'],
            'experience_description' => [Rule::requiredIf(! $usesCertificate), 'nullable', 'string', 'max:2000'],
            'agreed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'area' => 'area layanan',
            'service_id' => 'layanan utama',
            'radius' => 'radius layanan',
            'ktp' => 'foto KTP',
            'certificate' => 'sertifikat',
            'experience_years' => 'lama pengalaman',
            'reference_phone' => 'nomor referensi',
            'experience_description' => 'cerita pengalaman',
            'agreed' => 'pernyataan data benar',
        ];
    }

    public function register(RegisterTechnician $registerTechnician): void
    {
        $this->phone = User::normalizePhone($this->phone);
        $this->email = str($this->email)->lower()->trim()->toString();

        $validated = $this->validate();
        $usesCertificate = $this->competency === 'certificate';

        $user = $registerTechnician->handle(
            account: [
                'name' => $validated['name'],
                'business_name' => $validated['business_name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'area' => $validated['area'],
                'service_id' => (int) $validated['service_id'],
                'radius' => (int) $validated['radius'],
            ],
            ktp: $this->ktp,
            certificate: $usesCertificate ? $this->certificate : null,
            experience: $usesCertificate ? null : [
                'years' => (int) $validated['experience_years'],
                'reference_phone' => $validated['reference_phone'],
                'description' => $validated['experience_description'],
            ],
        );

        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('mitra.verification');
    }
}; ?>

<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-12">
    <a href="{{ route('mitra.login') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke login
    </a>

    <div class="mt-7">
        <x-eyebrow class="tracking-wider">Registrasi teknisi</x-eyebrow>
        <h1 class="mt-2 text-3xl font-extrabold">Buat akun teknisi terpercaya</h1>
        <p class="mt-2 text-ink-soft">Identitas dan kompetensi diperiksa sebelum profil dapat menerima pesanan pelanggan.</p>
    </div>

    <form wire:submit="register" class="mt-8 space-y-6">
        <x-card class="p-5 sm:p-7">
            <x-section-title icon="user-round" title="Data akun dan usaha" description="Gunakan informasi yang sesuai dengan identitas resmi." />
            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <x-form.input label="Nama lengkap sesuai KTP" name="name" wire:model="name" autocomplete="name" required />
                <x-form.input label="Nama usaha" name="business_name" wire:model="business_name" placeholder="Misal: Budi Teknik AC" required />
                <x-form.input label="Nomor WhatsApp" name="phone" type="tel" wire:model="phone" placeholder="0812 3456 7890" autocomplete="tel" required />
                <x-form.input label="Email" name="email" type="email" wire:model="email" autocomplete="email" required />
                <x-form.input label="Kata sandi" name="password" type="password" wire:model="password" autocomplete="new-password" required />
                <x-form.input label="Area layanan" name="area" wire:model="area" placeholder="Lowokwaru, Malang" required />
                <x-form.select label="Layanan utama" name="service_id" wire:model="service_id" required>
                    @foreach ($this->services as $service)
                        <option wire:key="opt-{{ $service->id }}" value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.select label="Radius layanan" name="radius" wire:model="radius" required>
                    @foreach (config('sejuk.technician_radius_options') as $option)
                        <option wire:key="radius-{{ $option }}" value="{{ $option }}">{{ $option }} km</option>
                    @endforeach
                </x-form.select>
            </div>
        </x-card>

        <x-card class="p-5 sm:p-7">
            <x-section-title icon="id-card" title="Verifikasi identitas" description="KTP wajib untuk memastikan identitas teknisi yang bergabung." />
            <div class="mt-6">
                <x-form.document-upload label="Foto KTP" name="ktp" :file="$ktp" hint="JPG atau PNG, maks. 5 MB. Pastikan seluruh data terlihat jelas." accept="image/png,image/jpeg" wire:model="ktp" />
            </div>
            <div class="mt-4 flex items-start gap-2 rounded-xl bg-frost p-4 text-sm text-ink-soft">
                <flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-ice" />
                Data identitas hanya digunakan untuk proses verifikasi dan tidak ditampilkan kepada pelanggan.
            </div>
        </x-card>

        <x-card class="p-5 sm:p-7">
            <x-section-title icon="award" title="Bukti kompetensi" description="Pilih salah satu: sertifikat teknisi atau pengalaman kerja yang dapat diperiksa." />
            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                <button type="button" wire:click="$set('competency', 'certificate')" @class(['rounded-2xl p-5 text-left ring-1', 'bg-frost ring-ice' => $competency === 'certificate', 'bg-white ring-line' => $competency !== 'certificate']) aria-pressed="{{ $competency === 'certificate' ? 'true' : 'false' }}">
                    <flux:icon.file-check-corner class="size-5 text-ice" />
                    <span class="mt-3 block font-bold">Saya punya sertifikat</span>
                    <span class="mt-1 block text-xs text-mute">Sertifikat BNSP, pelatihan refrigerasi, atau dokumen kompetensi lain.</span>
                </button>
                <button type="button" wire:click="$set('competency', 'experience')" @class(['rounded-2xl p-5 text-left ring-1', 'bg-frost ring-ice' => $competency === 'experience', 'bg-white ring-line' => $competency !== 'experience']) aria-pressed="{{ $competency === 'experience' ? 'true' : 'false' }}">
                    <flux:icon.briefcase-business class="size-5 text-ice" />
                    <span class="mt-3 block font-bold">Saya punya pengalaman</span>
                    <span class="mt-1 block text-xs text-mute">Jelaskan pengalaman servis dan cantumkan referensi pekerjaan.</span>
                </button>
            </div>

            @if ($competency === 'certificate')
                <div class="mt-5">
                    <x-form.document-upload label="Sertifikat kompetensi" name="certificate" :file="$certificate" hint="PDF, JPG, atau PNG, maks. 10 MB." accept=".pdf,image/png,image/jpeg" wire:model="certificate" />
                </div>
            @else
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-form.input label="Lama pengalaman (tahun)" name="experience_years" type="number" min="0" max="60" wire:model.live.debounce.300ms="experience_years" placeholder="5" required />
                    <x-form.input label="Nomor referensi pelanggan/usaha" name="reference_phone" type="tel" wire:model.live.debounce.300ms="reference_phone" placeholder="0813 9876 2210" required />
                    <x-form.textarea class="sm:col-span-2" label="Ceritakan pengalaman kerja" name="experience_description" rows="5" wire:model.live.debounce.400ms="experience_description" placeholder="Misal: menangani servis AC rumah dan kos sejak 2020, termasuk cuci rutin dan perbaikan kebocoran." required />
                </div>
            @endif
        </x-card>

        <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-white p-5 ring-1 ring-line">
            <input type="checkbox" wire:model.live="agreed" class="mt-1 size-4 accent-ice" />
            <span class="text-sm text-ink-soft">Saya menyatakan seluruh data benar dan bersedia diverifikasi oleh tim Sejuk. Data palsu dapat menyebabkan akun ditolak atau dinonaktifkan.</span>
        </label>
        @error('agreed') <p class="-mt-4 text-xs font-medium text-coral">{{ $message }}</p> @enderror

        <div class="flex flex-wrap items-center gap-4">
            <x-button type="submit" size="lg" icon-trailing="arrow-right" :disabled="! $this->canSubmit" wire:loading.attr="disabled" wire:target="register">
                Kirim untuk diverifikasi
            </x-button>
            @if (! $this->canSubmit)
                <p class="text-sm text-mute">Lengkapi KTP, bukti kompetensi, dan persetujuan data.</p>
            @endif
        </div>
    </form>
</div>
