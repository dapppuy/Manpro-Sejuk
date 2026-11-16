<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\VerificationStatus;
use App\Models\TechnicianDocument;
use App\Models\TechnicianProfile;
use App\Support\TechnicianSelection;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::customer'), Title('Menunggu verifikasi')] class extends Component {
    use WithFileUploads;

    public bool $isEditing = false;

    public string $competency = 'certificate';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $ktp = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $certificate = null;

    public ?int $experience_years = null;

    public string $reference_phone = '';

    public string $experience_description = '';

    public bool $agreesToPartnership = false;

    public function mount(): void
    {
        if ($this->profile?->verification_status === VerificationStatus::Approved) {
            $this->redirectRoute('mitra.dashboard');
        }
    }

    #[Computed]
    public function profile(): ?TechnicianProfile
    {
        return auth()->user()->technicianProfile?->load(['documents', 'experience']);
    }

    #[Computed]
    public function hasCertificate(): bool
    {
        $documents = $this->profile?->documents ?? collect();

        return $documents->contains(function ($document) {
            $typeVal = $document->type instanceof DocumentType ? $document->type->value : (string) $document->type;

            return $typeVal === 'sertifikat';
        });
    }

    public function startEditing(): void
    {
        $this->isEditing = true;
        $this->competency = $this->hasCertificate ? 'certificate' : 'experience';

        if ($this->profile?->experience) {
            $this->experience_years = $this->profile->experience->years;
            $this->reference_phone = $this->profile->experience->reference_phone;
            $this->experience_description = $this->profile->experience->description;
        }
    }

    public function cancelEditing(): void
    {
        $this->isEditing = false;
        $this->reset(['ktp', 'certificate', 'experience_years', 'reference_phone', 'experience_description']);
    }

    public function resubmit(): void
    {
        $usesCertificate = $this->competency === 'certificate';
        $hasExistingCertificate = $this->hasCertificate;

        $rules = [
            'ktp' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'competency' => ['required', Rule::in(['certificate', 'experience'])],
            'certificate' => [
                Rule::requiredIf($usesCertificate && ! $hasExistingCertificate),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],
            'experience_years' => [Rule::requiredIf(! $usesCertificate), 'nullable', 'integer', 'min:0', 'max:60'],
            'reference_phone' => [Rule::requiredIf(! $usesCertificate), 'nullable', 'string', 'max:20'],
            'experience_description' => [Rule::requiredIf(! $usesCertificate), 'nullable', 'string', 'max:2000'],
        ];

        $this->validate($rules, attributes: [
            'ktp' => 'foto KTP',
            'certificate' => 'sertifikat',
            'experience_years' => 'lama pengalaman',
            'reference_phone' => 'nomor referensi',
            'experience_description' => 'cerita pengalaman',
        ]);

        $profile = $this->profile;
        if (! $profile) {
            return;
        }

        DB::transaction(function () use ($profile, $usesCertificate) {
            // Perbarui KTP jika ada file baru diunggah
            if ($this->ktp) {
                $oldKtp = $profile->documents()->firstWhere('type', DocumentType::Ktp);
                if ($oldKtp) {
                    Storage::disk(TechnicianDocument::DISK)->delete($oldKtp->path);
                    $oldKtp->delete();
                }

                $profile->documents()->create([
                    'type' => DocumentType::Ktp,
                    'path' => $this->ktp->store("technician-documents/{$profile->id}", TechnicianDocument::DISK),
                    'status' => DocumentStatus::Pending,
                ]);
            } else {
                // Kembalikan status KTP ke pending jika sebelumnya ditolak
                $profile->documents()->where('type', DocumentType::Ktp)->update([
                    'status' => DocumentStatus::Pending,
                ]);
            }

            if ($usesCertificate) {
                if ($this->certificate) {
                    $oldCert = $profile->documents()->where('type', DocumentType::Sertifikat)->first();
                    if ($oldCert) {
                        Storage::disk(TechnicianDocument::DISK)->delete($oldCert->path);
                        $oldCert->delete();
                    }

                    $profile->documents()->create([
                        'type' => DocumentType::Sertifikat,
                        'path' => $this->certificate->store("technician-documents/{$profile->id}", TechnicianDocument::DISK),
                        'status' => DocumentStatus::Pending,
                    ]);
                } else {
                    $profile->documents()->where('type', DocumentType::Sertifikat)->update([
                        'status' => DocumentStatus::Pending,
                    ]);
                }

                // Hapus data pengalaman lama jika beralih ke jalur sertifikat
                $profile->experience()?->delete();
                $profile->update(['years_experience' => 0]);
            } else {
                // Jalur pengalaman: hapus sertifikat jika sebelumnya ada
                $oldCerts = $profile->documents()->where('type', DocumentType::Sertifikat)->get();
                foreach ($oldCerts as $cert) {
                    Storage::disk(TechnicianDocument::DISK)->delete($cert->path);
                    $cert->delete();
                }

                $profile->experience()->updateOrCreate(
                    ['technician_id' => $profile->id],
                    [
                        'years' => (int) $this->experience_years,
                        'reference_phone' => $this->reference_phone,
                        'description' => $this->experience_description,
                    ]
                );

                $profile->update([
                    'years_experience' => (int) $this->experience_years,
                ]);
            }

            // Kembalikan status akun ke Pending
            $profile->update([
                'verification_status' => VerificationStatus::Pending,
                'verification_note' => null,
            ]);
        });

        // Invalidate Livewire computed cache agar tampilan langsung ter-refresh dengan data baru
        unset($this->profile);
        unset($this->hasCertificate);

        $this->isEditing = false;
        $this->reset(['ktp', 'certificate', 'experience_years', 'reference_phone', 'experience_description']);
        Flux::toast(variant: 'success', text: 'Data verifikasi berhasil dikirim ulang dan sedang ditinjau kembali oleh tim Sejuk.');
    }

    #[Computed]
    public function selection(): ?TechnicianSelection
    {
        return $this->profile === null ? null : TechnicianSelection::for($this->profile);
    }

    /**
     * The applicant accepts the partnership agreement after the orientation call (selection stage 6).
     */
    public function acceptAgreement(): void
    {
        $this->validate(['agreesToPartnership' => ['accepted']], ['agreesToPartnership.accepted' => 'Centang persetujuan perjanjian kemitraan terlebih dahulu.']);

        $profile = $this->profile;
        abort_if($profile === null || $profile->verification_status !== VerificationStatus::Pending, 403);

        $profile->update(['agreement_accepted_at' => now()]);
        unset($this->profile, $this->selection);

        Flux::toast(variant: 'success', text: 'Perjanjian kemitraan disetujui. Tim Sejuk akan segera mengaktifkan akunmu.');
    }
}; ?>

@php
    $rejected = $this->profile?->verification_status === VerificationStatus::Rejected;
    $hasCert = $this->hasCertificate;
@endphp

<div class="mx-auto flex min-h-[calc(100vh-8rem)] max-w-2xl items-center px-4 py-10 sm:px-6 sm:py-12">
    <x-card class="w-full p-6 text-center sm:p-8">
        @if ($rejected)
            @if (! $isEditing)
                <span class="mx-auto grid size-16 place-items-center rounded-full bg-coral-soft text-coral">
                    <flux:icon.circle-alert class="size-8" />
                </span>
                <p class="mt-5 text-xs font-bold tracking-wider text-coral uppercase">Verifikasi ditolak</p>
                <h1 class="mt-2 text-2xl font-extrabold sm:text-3xl">Data akun belum bisa disetujui</h1>
                
                <div class="mx-auto mt-4 max-w-lg rounded-2xl bg-coral-soft/50 p-4 text-left ring-1 ring-coral/20">
                    <p class="text-xs font-bold uppercase tracking-wider text-coral">Alasan Penolakan dari Admin:</p>
                    <p class="mt-1 text-sm font-semibold text-ink">
                        {{ $this->profile->verification_note ?: 'Dokumen atau data yang diunggah belum memenuhi syarat verifikasi Sejuk.' }}
                    </p>
                </div>
                
                <p class="mx-auto mt-4 max-w-lg text-sm text-mute">
                    Anda dapat memperbarui foto KTP atau bukti kompetensi yang diminta tanpa perlu mendaftar ulang akun dari awal.
                </p>

                <div class="mt-7 flex flex-wrap justify-center gap-3">
                    <x-button class="cursor-pointer" wire:click="startEditing" icon="pencil-square">
                        Perbaiki Dokumen & Ajukan Banding
                    </x-button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button type="submit" variant="ghost" icon="log-out">Keluar</x-button>
                    </form>
                </div>
            @else
                <div class="text-left">
                    <button type="button" wire:click="cancelEditing" class="inline-flex items-center gap-2 text-sm font-semibold text-ink-soft hover:text-ink">
                        <flux:icon.arrow-left class="size-4" /> Batal dan kembali
                    </button>

                    <h1 class="mt-4 text-2xl font-extrabold sm:text-3xl">Perbaiki Dokumen Pendaftaran</h1>
                    <p class="mt-1 text-sm text-mute">Unggah ulang dokumen yang perlu diperbaiki sesuai catatan admin di bawah.</p>

                    <div class="mt-4 rounded-xl bg-coral-soft/60 p-4 text-xs text-coral">
                        <strong>Catatan Admin Sebelumnya:</strong> {{ $this->profile->verification_note }}
                    </div>

                    <form wire:submit="resubmit" class="mt-6 space-y-6">
                        <x-card class="p-5 sm:p-6">
                            <x-section-title icon="id-card" title="Foto KTP" description="Unggah foto KTP baru jika foto sebelumnya buram atau terpotong (kosongkan jika sudah benar)." />
                            <div class="mt-4">
                                <x-form.document-upload label="Foto KTP Baru" name="ktp" :file="$ktp" hint="JPG atau PNG, maks. 5 MB." accept="image/png,image/jpeg" wire:model="ktp" />
                            </div>
                        </x-card>

                        <x-card class="p-5 sm:p-6">
                            <x-section-title icon="award" title="Bukti kompetensi" description="Pilih salah satu: sertifikat resmi (jalur cepat) atau pengalaman kerja (perlu video call)." />
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <button type="button" wire:click="$set('competency', 'certificate')" @class(['rounded-2xl p-4 text-left ring-1 transition', 'bg-frost ring-ice' => $competency === 'certificate', 'bg-white ring-line hover:bg-paper/50' => $competency !== 'certificate'])>
                                    <div class="flex items-center justify-between">
                                        <flux:icon.file-check-corner class="size-5 text-ice" />
                                        <span class="rounded-full bg-leaf-soft px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-leaf">Jalur Cepat</span>
                                    </div>
                                    <span class="mt-2 block font-bold">Saya punya sertifikat</span>
                                    <span class="mt-1 block text-xs text-mute">Lampirkan sertifikat resmi BNSP/pelatihan. Tanpa video call.</span>
                                </button>
                                <button type="button" wire:click="$set('competency', 'experience')" @class(['rounded-2xl p-4 text-left ring-1 transition', 'bg-frost ring-ice' => $competency === 'experience', 'bg-white ring-line hover:bg-paper/50' => $competency !== 'experience'])>
                                    <div class="flex items-center justify-between">
                                        <flux:icon.briefcase-business class="size-5 text-ice" />
                                        <span class="rounded-full bg-sun-soft px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-sun-ink">Perlu Video Call</span>
                                    </div>
                                    <span class="mt-2 block font-bold">Saya punya pengalaman</span>
                                    <span class="mt-1 block text-xs text-mute">Verifikasi alat kerja via video call WhatsApp.</span>
                                </button>
                            </div>

                            @if ($competency === 'certificate')
                                <div class="mt-5">
                                    <x-form.document-upload label="Sertifikat kompetensi" name="certificate" :file="$certificate" hint="PDF, JPG, atau PNG, maks. 10 MB. Pastikan nomor registrasi/lembaga terbaca." accept=".pdf,image/png,image/jpeg" wire:model="certificate" :required="! $this->hasCertificate" />
                                </div>
                            @else
                                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                    <x-form.input label="Lama pengalaman (tahun)" name="experience_years" type="number" min="0" max="60" wire:model.live.debounce.300ms="experience_years" placeholder="5" required />
                                    <x-form.input label="Nomor referensi pelanggan/usaha" name="reference_phone" type="tel" wire:model.live.debounce.300ms="reference_phone" placeholder="0813 9876 2210" required />
                                    <x-form.textarea class="sm:col-span-2" label="Ceritakan pengalaman kerja" name="experience_description" rows="4" wire:model.live.debounce.400ms="experience_description" placeholder="Ceritakan pengalaman servis AC Anda..." required />
                                </div>
                            @endif
                        </x-card>

                        <div class="flex flex-wrap items-center gap-3">
                            <x-button type="submit" wire:loading.attr="disabled" wire:target="resubmit" icon="arrow-path">
                                Kirim Ulang Verifikasi
                            </x-button>
                            <x-button type="button" variant="ghost" wire:click="cancelEditing">Batal</x-button>
                        </div>
                    </form>
                </div>
            @endif
        @else
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-sun-soft text-sun-ink">
                <flux:icon.shield-check class="size-8" />
            </span>
            <p class="mt-5 text-xs font-bold tracking-wider text-sun-ink uppercase">Menunggu verifikasi</p>
            <h1 class="mt-2 text-2xl font-extrabold sm:text-3xl">Data akun berhasil dikirim</h1>
            
            @if ($hasCert)
                <p class="mx-auto mt-3 max-w-lg leading-relaxed text-ink-soft">
                    Tim Sejuk sedang mencocokkan data KTP dan memeriksa keabsahan dokumen sertifikat Anda (Jalur Cepat). Akun baru dapat menerima pesanan setelah proses ini selesai.
                </p>
            @else
                <div class="mx-auto mt-3 max-w-lg rounded-2xl bg-sun-soft/50 p-4 text-sm leading-relaxed text-ink-soft ring-1 ring-sun-ink/20">
                    <p class="font-bold text-sun-ink">Jalur Pengalaman (Perlu Video Call):</p>
                    <p class="mt-1">Tim Sejuk akan menghubungi Anda melalui <strong>Video Call WhatsApp</strong> ke nomor <strong class="text-ink">{{ auth()->user()->phone }}</strong> untuk memverifikasi alat kerja fisik dan keaslian pengalaman kerja Anda.</p>
                </div>
            @endif

            @php
                $selection = $this->selection;
                $currentStage = $selection->currentStage();
            @endphp
            <div class="mt-8 text-left">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-lg font-bold">Tahapan seleksi</p>
                        <p class="text-sm text-mute">Selain pemeriksaan dokumen, seleksi dilakukan lewat WhatsApp dengan tim Sejuk.</p>
                    </div>
                    <x-pill tone="sun">{{ $selection->passedCount() }}/{{ count($selection->stages()) }} selesai</x-pill>
                </div>

                <ol class="mt-5 space-y-3">
                    @foreach ($selection->stages() as $key => $stage)
                        @php
                            $result = $selection->result($key);
                            $isCurrent = $key === $currentStage;
                        @endphp
                        <li wire:key="stage-{{ $key }}" @class(['rounded-2xl p-4 ring-1', 'bg-leaf-soft/40 ring-leaf/20' => $result, 'bg-frost/50 ring-ice/40' => $isCurrent, 'bg-paper ring-transparent' => ! $result && ! $isCurrent])>
                            <div class="flex items-start gap-3">
                                <span @class(['grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold ring-1', 'bg-leaf text-white ring-leaf' => $result, 'bg-ice text-white ring-ice' => $isCurrent, 'bg-white text-mute ring-line' => ! $result && ! $isCurrent])>
                                    @if ($result) <flux:icon.check class="size-3.5" /> @else {{ $loop->iteration }} @endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="font-bold">{{ $stage['title'] }}</p>
                                        <span class="text-xs font-semibold {{ $result ? 'text-leaf' : ($isCurrent ? 'text-ice-deep' : 'text-mute') }}">
                                            {{ $result ? ($selection->isSkipped($key) ? 'Tidak diperlukan' : 'Lolos '.CarbonImmutable::parse($result['passed_at'])->translatedFormat('j M')) : ($isCurrent ? 'Tahap sekarang' : 'Menunggu') }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-mute">{{ $stage['channel'] }}{{ ! $result && $selection->canSkip($key) ? ' · bisa tidak diperlukan karena kamu punya sertifikat' : '' }}</p>
                                    <p class="mt-1.5 text-sm text-ink-soft">{{ $stage['summary'] }}</p>
                                    @if ($isCurrent || ! $result)
                                        <details class="mt-2 text-sm" @if ($isCurrent) open @endif>
                                            <summary class="cursor-pointer text-xs font-semibold text-ice">Apa yang dilakukan?</summary>
                                            <ul class="mt-2 list-inside list-disc space-y-1 text-ink-soft">
                                                @foreach ($stage['steps'] as $step)
                                                    <li wire:key="step-{{ $key }}-{{ $loop->index }}">{{ $step }}</li>
                                                @endforeach
                                            </ul>
                                            <p class="mt-2 text-xs text-mute">Lulus jika: {{ $stage['passes'] }}</p>
                                        </details>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach

                    <li @class(['rounded-2xl p-4 ring-1', 'bg-leaf-soft/40 ring-leaf/20' => $this->profile->agreement_accepted_at, 'bg-frost/50 ring-ice/40' => ! $this->profile->agreement_accepted_at && $currentStage === null, 'bg-paper ring-transparent' => ! $this->profile->agreement_accepted_at && $currentStage !== null])>
                        <p class="font-bold">Persetujuan perjanjian kemitraan</p>
                        @if ($this->profile->agreement_accepted_at)
                            <p class="mt-1 text-sm text-leaf">Disetujui {{ $this->profile->agreement_accepted_at->translatedFormat('j F Y, H.i') }}. Akunmu tinggal diaktifkan tim Sejuk.</p>
                        @else
                            <p class="mt-1 text-sm text-ink-soft">Baca <a href="{{ route('pages.partnership') }}" target="_blank" class="font-semibold text-ice hover:underline">perjanjian kemitraan</a>, lalu setujui setelah orientasi lewat WhatsApp.</p>
                            <label class="mt-3 flex items-start gap-2 text-sm">
                                <input type="checkbox" wire:model="agreesToPartnership" class="mt-0.5 size-4 accent-ice" />
                                <span>Saya sudah mengikuti orientasi dan menyetujui perjanjian kemitraan Sejuk, termasuk pembayaran hanya lewat Sejuk.</span>
                            </label>
                            @error('agreesToPartnership') <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
                            <x-button size="sm" class="mt-3" icon="check" wire:click="acceptAgreement">Setujui perjanjian</x-button>
                        @endif
                    </li>

                    <li class="rounded-2xl bg-paper p-4">
                        <p class="font-bold">Aktif & masa percobaan</p>
                        <p class="mt-1 text-sm text-ink-soft">Setelah diaktifkan, profilmu tampil di pencarian. {{ config('sejuk.probation_orders') }} pesanan pertama dipantau tim Sejuk untuk memastikan kualitas layanan.</p>
                    </li>
                </ol>

                <div class="mt-6 flex flex-col items-start gap-3 rounded-2xl bg-leaf-soft/50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-ink-soft">Tim seleksi akan menghubungi <strong class="text-ink">{{ auth()->user()->phone }}</strong>. Ada pertanyaan atau ingin mengatur jadwal?</p>
                    <a href="{{ $selection->technicianMessageUrl() }}" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center gap-2 rounded-full bg-leaf px-4 py-2 text-sm font-bold text-white hover:opacity-95">
                        <flux:icon.message-circle class="size-4" /> Chat tim seleksi
                    </a>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button type="submit" variant="ghost" icon="log-out">Keluar</x-button>
                </form>
            </div>
        @endif
    </x-card>
</div>
