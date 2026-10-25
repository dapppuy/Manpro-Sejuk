<?php

use App\Actions\Technician\ReviewTechnicianVerification;
use App\Enums\DocumentType;
use App\Enums\TechnicianBadge;
use App\Enums\VerificationStatus;
use App\Models\TechnicianProfile;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin'), Title('Detail verifikasi')] class extends Component {
    public TechnicianProfile $technician;

    public string $badge = 'identitas';

    public string $reason = '';

    public function mount(TechnicianProfile $technician): void
    {
        $this->technician = $technician->load(['user', 'services', 'serviceAreas', 'documents', 'experience']);
        $this->badge = $technician->documents->contains('type', DocumentType::Sertifikat) ? 'sertifikat' : 'identitas';
    }

    public function approve(ReviewTechnicianVerification $review): void
    {
        $this->validate(['badge' => ['required', Rule::enum(TechnicianBadge::class)]]);

        $review->approve($this->technician, TechnicianBadge::from($this->badge), auth()->user());

        Flux::toast(variant: 'success', text: $this->technician->business_name.' disetujui dan dipublikasikan.');
        $this->redirectRoute('admin.verifications.index', navigate: true);
    }

    public function reject(ReviewTechnicianVerification $review): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:10', 'max:500']], attributes: ['reason' => 'alasan penolakan']);

        $review->reject($this->technician, $this->reason, auth()->user());

        Flux::toast(variant: 'warning', text: $this->technician->business_name.' ditolak.');
        $this->redirectRoute('admin.verifications.index', navigate: true);
    }
}; ?>

@php
    $technician = $this->technician;
    $ktp = $technician->documents->firstWhere('type', DocumentType::Ktp);
    $certificate = $technician->documents->firstWhere('type', DocumentType::Sertifikat);
@endphp

<div>
    <a href="{{ route('admin.verifications.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke antrean
    </a>

    <div class="mt-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-mute">Didaftarkan {{ $technician->created_at?->translatedFormat('j F Y, H.i') }}</p>
            <h1 class="mt-1 text-2xl font-extrabold sm:text-3xl">{{ $technician->business_name }}</h1>
        </div>
        <x-pill :tone="match ($technician->verification_status) { VerificationStatus::Approved => 'leaf', VerificationStatus::Rejected => 'coral', default => 'sun' }">
            {{ $technician->verification_status->label() }}
        </x-pill>
    </div>

    <div class="mt-7 grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            <x-card class="p-5 sm:p-6">
                <x-section-title icon="user-round" title="Data akun dan usaha" />
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'Nama sesuai KTP' => $technician->user->name,
                        'WhatsApp' => $technician->user->phone,
                        'Email' => $technician->user->email,
                        'Area layanan' => $technician->base_area,
                        'Layanan' => $technician->services->pluck('name')->implode(', '),
                        'Radius' => $technician->service_radius_km.' km',
                    ] as $label => $value)
                        <div wire:key="d-{{ $loop->index }}">
                            <dt class="text-xs text-mute">{{ $label }}</dt>
                            <dd class="mt-1 font-semibold break-words">{{ $value ?: '–' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card class="p-5 sm:p-6">
                <x-section-title icon="id-card" title="Foto KTP" description="Cocokkan nama dan foto dengan data akun." />
                @if ($ktp)
                    <a href="{{ route('admin.documents.show', $ktp) }}" target="_blank" class="mt-5 block">
                        <img src="{{ route('admin.documents.show', $ktp) }}" alt="Foto KTP {{ $technician->user->name }}" class="max-h-80 w-full rounded-2xl bg-paper object-contain ring-1 ring-line" />
                    </a>
                @else
                    <p class="mt-5 text-sm text-coral">KTP tidak ditemukan.</p>
                @endif
            </x-card>

            <x-card class="p-5 sm:p-6">
                <x-section-title icon="award" title="Bukti kompetensi" :description="$certificate ? 'Sertifikat kompetensi' : 'Pengalaman kerja tanpa sertifikat'" />
                @if ($certificate)
                    @if ($certificate->isPdf())
                        <x-button variant="secondary" class="mt-5" icon="file-text" :href="route('admin.documents.show', $certificate)" target="_blank">Buka sertifikat (PDF)</x-button>
                    @else
                        <a href="{{ route('admin.documents.show', $certificate) }}" target="_blank" class="mt-5 block">
                            <img src="{{ route('admin.documents.show', $certificate) }}" alt="Sertifikat {{ $technician->user->name }}" class="max-h-96 w-full rounded-2xl bg-paper object-contain ring-1 ring-line" />
                        </a>
                    @endif
                @elseif ($technician->experience)
                    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs text-mute">Lama pengalaman</dt><dd class="mt-1 font-semibold">{{ $technician->experience->years }} tahun</dd></div>
                        <div><dt class="text-xs text-mute">Nomor referensi</dt><dd class="mt-1 font-semibold">{{ $technician->experience->reference_phone }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-mute">Cerita pengalaman</dt><dd class="mt-1 text-sm leading-relaxed whitespace-pre-line text-ink-soft">{{ $technician->experience->description }}</dd></div>
                    </dl>
                @endif
            </x-card>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-10 lg:self-start">
            @if ($technician->verification_status === VerificationStatus::Rejected && $technician->verification_note)
                <div class="rounded-2xl bg-coral-soft p-4 text-sm text-coral">
                    <p class="font-bold">Alasan penolakan sebelumnya</p>
                    <p class="mt-1">{{ $technician->verification_note }}</p>
                </div>
            @endif

            <x-card class="p-5">
                <p class="font-bold">Setujui dengan badge</p>
                <div class="mt-4 space-y-2">
                    @foreach (TechnicianBadge::cases() as $case)
                        <label wire:key="badge-{{ $case->value }}" class="flex cursor-pointer items-start gap-3 rounded-xl p-3 ring-1 ring-line has-checked:bg-frost has-checked:ring-ice">
                            <input type="radio" wire:model="badge" value="{{ $case->value }}" class="mt-1 size-4 accent-ice" />
                            <span>
                                <x-badge-chip :badge="$case" />
                                <span class="mt-1 block text-xs text-mute">{{ $case->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('badge') <p class="mt-2 text-xs text-coral">{{ $message }}</p> @enderror
                <x-button class="mt-4 w-full" icon="circle-check" wire:click="approve" wire:confirm="Setujui dan publikasikan profil teknisi ini?">Setujui & publikasikan</x-button>
            </x-card>

            <x-card class="p-5">
                <p class="font-bold">Tolak pendaftaran</p>
                <x-form.textarea class="mt-3" label="Alasan (dikirim ke teknisi)" name="reason" rows="3" wire:model="reason" placeholder="Misal: foto KTP buram, mohon kirim ulang." />
                <x-button variant="danger" class="mt-3 w-full" wire:click="reject">Tolak</x-button>
            </x-card>
        </aside>
    </div>
</div>
