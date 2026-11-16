<?php

use App\Actions\Technician\ReviewTechnicianVerification;
use App\Enums\DocumentType;
use App\Enums\TechnicianBadge;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\TechnicianProfile;
use App\Notifications\TechnicianSelectionUpdated;
use App\Support\TechnicianSelection;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin'), Title('Detail verifikasi')] class extends Component {
    public TechnicianProfile $technician;

    public string $badge = 'identitas';

    public string $reason = '';

    /**
     * Admin notes per selection stage, e.g. the interview score.
     *
     * @var array<string, string>
     */
    public array $stageNotes = [];

    public function mount(TechnicianProfile $technician): void
    {
        $this->technician = $technician->load(['user', 'services', 'serviceAreas', 'documents', 'experience']);
        $hasCert = $this->technician->documents->contains(fn ($d) => ($d->type instanceof DocumentType ? $d->type->value : (string) $d->type) === 'sertifikat');
        $this->badge = $hasCert ? 'sertifikat' : 'identitas';
    }

    public function passStage(string $stage): void
    {
        $selection = TechnicianSelection::for($this->technician);
        abort_unless(array_key_exists($stage, $selection->stages()), 404);

        $this->validate(["stageNotes.{$stage}" => ['nullable', 'string', 'max:500']], attributes: ["stageNotes.{$stage}" => 'catatan']);

        $selection->markPassed($stage, auth()->user(), ($this->stageNotes[$stage] ?? '') ?: null);
        AuditLog::record('technician.stage_passed', "{$this->technician->business_name} lolos tahap ".TechnicianSelection::STAGES[$stage]['title'], $this->technician, ['note' => $this->stageNotes[$stage] ?? null]);
        $this->technician->user->notify(new TechnicianSelectionUpdated($this->technician, $stage));

        Flux::toast(variant: 'success', text: 'Tahap '.TechnicianSelection::STAGES[$stage]['title'].' ditandai lolos.');
    }

    public function skipStage(string $stage): void
    {
        $selection = TechnicianSelection::for($this->technician);
        abort_unless($selection->canSkip($stage), 422);

        $this->validate(["stageNotes.{$stage}" => ['nullable', 'string', 'max:500']], attributes: ["stageNotes.{$stage}" => 'catatan']);

        $selection->skip($stage, auth()->user(), ($this->stageNotes[$stage] ?? '') ?: null);
        AuditLog::record('technician.stage_skipped', "Tahap ".TechnicianSelection::STAGES[$stage]['title']." untuk {$this->technician->business_name} dilewati (jalur sertifikat)", $this->technician);
        $this->technician->user->notify(new TechnicianSelectionUpdated($this->technician, $stage));

        Flux::toast(text: 'Tahap '.TechnicianSelection::STAGES[$stage]['title'].' dilewati.');
    }

    public function undoStage(string $stage): void
    {
        abort_unless(array_key_exists($stage, TechnicianSelection::STAGES), 404);

        TechnicianSelection::for($this->technician)->undo($stage);
        AuditLog::record('technician.stage_undone', "Tanda lolos tahap ".TechnicianSelection::STAGES[$stage]['title']." untuk {$this->technician->business_name} dibatalkan", $this->technician);
    }

    public function approve(ReviewTechnicianVerification $review): void
    {
        $this->validate(['badge' => ['required', Rule::enum(TechnicianBadge::class)->only(TechnicianBadge::verified())]]);

        if (! TechnicianSelection::for($this->technician)->isReadyToApprove()) {
            $this->addError('badge', 'Selesaikan semua tahap seleksi dan tunggu teknisi menyetujui perjanjian kemitraan.');

            return;
        }

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
    $selection = TechnicianSelection::for($technician);
    $currentStage = $selection->currentStage();
    $ktp = $technician->documents->first(fn ($d) => ($d->type instanceof DocumentType ? $d->type->value : (string) $d->type) === 'ktp');
    $certificate = $technician->documents->first(fn ($d) => ($d->type instanceof DocumentType ? $d->type->value : (string) $d->type) === 'sertifikat');

    // Format nomor WhatsApp untuk Video Call
    $rawPhone = $technician->user->phone ?? '';
    $cleanPhone = preg_replace('/\D+/', '', $rawPhone);
    if (str_starts_with($cleanPhone, '0')) {
        $cleanPhone = '62' . substr($cleanPhone, 1);
    }
    $waMessage = urlencode("Halo {$technician->user->name}, saya Admin Sejuk ingin melakukan video call singkat untuk verifikasi alat kerja dan akun mitra Anda.");
    $waUrl = $cleanPhone ? "https://wa.me/{$cleanPhone}?text={$waMessage}" : null;

    $rawRefPhone = $technician->experience?->reference_phone ?? '';
    $cleanRefPhone = preg_replace('/\D+/', '', $rawRefPhone);
    if (str_starts_with($cleanRefPhone, '0')) {
        $cleanRefPhone = '62' . substr($cleanRefPhone, 1);
    }
    $waRefMessage = urlencode("Halo, kami dari tim verifikasi Sejuk. Apakah benar Bapak/Ibu pernah menggunakan jasa servis AC dari teknisi {$technician->user->name}?");
    $waRefUrl = $cleanRefPhone ? "https://wa.me/{$cleanRefPhone}?text={$waRefMessage}" : null;
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
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <x-section-title icon="list-checks" title="Tahapan seleksi" :description="($selection->usesCertificate() ? 'Jalur sertifikat: wawancara, uji praktik, dan cek referensi boleh dijalankan atau dilewati sesuai penilaianmu. ' : '').'Tahap 2 dst. dilakukan lewat WhatsApp, lalu catat hasilnya di sini.'" />
                    <x-pill :tone="$selection->isReadyToApprove() ? 'leaf' : 'sun'">{{ $selection->passedCount() }}/{{ count($selection->stages()) }} tahap</x-pill>
                </div>
                <ol class="mt-5 space-y-3">
                    @foreach ($selection->stages() as $key => $stage)
                        @php $result = $selection->result($key); @endphp
                        <li wire:key="stage-{{ $key }}" @class(['rounded-2xl p-4 ring-1', 'bg-leaf-soft/40 ring-leaf/20' => $result, 'bg-frost/40 ring-ice/30' => ! $result && $key === $currentStage, 'ring-line' => ! $result && $key !== $currentStage])>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-bold">{{ $loop->iteration }}. {{ $stage['title'] }}</p>
                                    <p class="text-xs text-mute">{{ $stage['channel'] }} · Lulus jika: {{ $stage['passes'] }}</p>
                                </div>
                                @if ($result)
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold {{ $selection->isSkipped($key) ? 'text-mute' : 'text-leaf' }}"><flux:icon.circle-check class="size-4" /> {{ $selection->isSkipped($key) ? 'Dilewati' : 'Lolos' }} {{ CarbonImmutable::parse($result['passed_at'])->translatedFormat('j M, H.i') }}</span>
                                @elseif ($selection->canSkip($key))
                                    <span class="rounded-full bg-frost px-2.5 py-0.5 text-[11px] font-bold text-ice-deep">Opsional · bersertifikat</span>
                                @endif
                            </div>
                            <ul class="mt-2 list-inside list-disc space-y-0.5 text-xs text-ink-soft">
                                @foreach ($stage['steps'] as $step)
                                    <li wire:key="step-{{ $key }}-{{ $loop->index }}">{{ $step }}</li>
                                @endforeach
                            </ul>
                            @if ($result)
                                @if ($result['note'])
                                    <p class="mt-2 text-sm">Catatan: {{ $result['note'] }}</p>
                                @endif
                                @if ($technician->verification_status === VerificationStatus::Pending)
                                    <button type="button" wire:click="undoStage('{{ $key }}')" class="mt-2 text-xs font-semibold text-mute hover:text-coral">Batalkan</button>
                                @endif
                            @elseif ($technician->verification_status === VerificationStatus::Pending)
                                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <input type="text" wire:model="stageNotes.{{ $key }}" placeholder="Catatan hasil (opsional)" class="min-w-0 flex-1 rounded-xl border-0 bg-white px-3 py-2 text-sm ring-1 ring-line" />
                                    <div class="flex gap-2">
                                        @if ($key !== 'dokumen' && ($url = $selection->adminMessageUrl($key)))
                                            <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-xl bg-leaf px-3 py-2 text-xs font-bold text-white hover:opacity-95">
                                                <flux:icon.message-circle class="size-4" /> Chat WA
                                            </a>
                                        @endif
                                        <x-button size="sm" icon="check" wire:click="passStage('{{ $key }}')">Tandai lolos</x-button>
                                        @if ($selection->canSkip($key))
                                            <x-button size="sm" variant="secondary" wire:click="skipStage('{{ $key }}')" wire:confirm="Lewati tahap ini karena sertifikatnya sudah valid?">Lewati</x-button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                    <li @class(['rounded-2xl p-4 text-sm ring-1', 'bg-leaf-soft/40 ring-leaf/20' => $technician->agreement_accepted_at, 'ring-line' => ! $technician->agreement_accepted_at])>
                        <p class="font-bold">Perjanjian kemitraan</p>
                        <p class="text-ink-soft">{{ $technician->agreement_accepted_at ? 'Disetujui teknisi '.$technician->agreement_accepted_at->translatedFormat('j M Y, H.i') : 'Belum disetujui. Teknisi menyetujuinya di halaman status pendaftaran setelah orientasi.' }}</p>
                    </li>
                </ol>
            </x-card>

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
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <x-section-title icon="award" title="Bukti kompetensi" :description="$certificate ? 'Jalur Sertifikat Resmi' : 'Jalur Pengalaman Kerja'" />
                    @if ($certificate)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-leaf-soft px-3 py-1 text-xs font-semibold text-leaf">
                            <flux:icon.check-circle class="size-3.5" /> Jalur Cepat (Fast-Track)
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-sun-soft px-3 py-1 text-xs font-semibold text-sun-ink">
                            <flux:icon.video-camera class="size-3.5" /> Wajib Video Call
                        </span>
                    @endif
                </div>

                @if ($certificate)
                    <div class="mt-4 rounded-xl bg-frost p-4 text-xs leading-relaxed text-ink-soft">
                        <p class="font-bold text-ink">Panduan Verifikasi Jalur Cepat (Fast-Track):</p>
                        <p class="mt-1">Calon mitra melampirkan sertifikat kompetensi resmi. Cocokkan nama sertifikat dengan KTP dan pastikan nomor registrasi/lembaga penerbit resmi (BNSP/BLK/Brand). Tidak memerlukan video call jika dokumen valid.</p>
                    </div>

                    @if ($certificate->isPdf())
                        <x-button variant="secondary" class="mt-5" icon="file-text" :href="route('admin.documents.show', $certificate)" target="_blank">Buka sertifikat (PDF)</x-button>
                    @else
                        <a href="{{ route('admin.documents.show', $certificate) }}" target="_blank" class="mt-5 block">
                            <img src="{{ route('admin.documents.show', $certificate) }}" alt="Sertifikat {{ $technician->user->name }}" class="max-h-96 w-full rounded-2xl bg-paper object-contain ring-1 ring-line" />
                        </a>
                    @endif
                @elseif ($technician->experience)
                    <div class="mt-4 rounded-2xl bg-sun-soft/40 p-4 text-xs text-ink-soft ring-1 ring-sun-ink/20">
                        <div class="flex items-center gap-2 font-bold text-sun-ink">
                            <flux:icon.video-camera class="size-4" /> SOP Verifikasi Video Call WhatsApp (2–3 Menit):
                        </div>
                        <ol class="mt-2 list-inside list-decimal space-y-1">
                            <li>Cocokkan wajah calon teknisi secara langsung dengan <strong>Foto KTP</strong> di atas.</li>
                            <li>Minta teknisi mengarahkan kamera ke <strong>alat kerja fisik</strong> (manifold, pompa jet cleaner, dsb.).</li>
                            <li>Ajukan 1 pertanyaan praktis seputar servis AC lapangan.</li>
                        </ol>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($waUrl)
                                <a href="{{ $waUrl }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-leaf px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-95">
                                    <flux:icon.video-camera class="size-4" /> Hubungi Video Call WhatsApp
                                </a>
                            @endif
                            @if ($waRefUrl)
                                <a href="{{ $waRefUrl }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-white px-3.5 py-2 text-xs font-semibold text-ink-soft ring-1 ring-line hover:bg-paper">
                                    <flux:icon.phone class="size-3.5" /> Hubungi Nomor Referensi
                                </a>
                            @endif
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs text-mute">Lama pengalaman</dt><dd class="mt-1 font-semibold">{{ $technician->experience->years }} tahun</dd></div>
                        <div>
                            <dt class="text-xs text-mute">Nomor referensi</dt>
                            <dd class="mt-1 flex items-center gap-2 font-semibold">
                                {{ $technician->experience->reference_phone }}
                                @if ($waRefUrl)
                                    <a href="{{ $waRefUrl }}" target="_blank" class="text-xs font-normal text-ice hover:underline">Chat WA</a>
                                @endif
                            </dd>
                        </div>
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
                @if ($certificate)
                    <div class="mt-2 rounded-xl bg-leaf-soft p-2.5 text-xs text-leaf">
                        ✅ <strong>Jalur Sertifikat:</strong> Disarankan pilih <strong>Bersertifikat</strong> jika dokumen valid.
                    </div>
                @else
                    <div class="mt-2 rounded-xl bg-frost p-2.5 text-xs text-ink-soft">
                        💡 <strong>Jalur Pengalaman:</strong> Disarankan pilih <strong>Identitas Terverifikasi</strong> setelah lolos video call.
                    </div>
                @endif
                <div class="mt-4 space-y-2">
                    @foreach (TechnicianBadge::verified() as $case)
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
                @if (! $selection->isReadyToApprove())
                    <p class="mt-4 rounded-xl bg-sun-soft p-2.5 text-xs text-sun-ink">Tombol aktif setelah semua tahap seleksi lolos dan perjanjian kemitraan disetujui.</p>
                @endif
                <x-button class="mt-4 w-full" icon="circle-check" wire:click="approve" wire:confirm="Setujui dan publikasikan profil teknisi ini?" :disabled="! $selection->isReadyToApprove()">Setujui & publikasikan</x-button>
            </x-card>

            <x-card class="p-5">
                <p class="font-bold">Tolak pendaftaran</p>
                <x-form.textarea class="mt-3" label="Alasan (dikirim ke teknisi)" name="reason" rows="3" wire:model="reason" placeholder="Misal: foto KTP buram, mohon kirim ulang." />
                <x-button variant="danger" class="mt-3 w-full" wire:click="reject">Tolak</x-button>
            </x-card>
        </aside>
    </div>
</div>
