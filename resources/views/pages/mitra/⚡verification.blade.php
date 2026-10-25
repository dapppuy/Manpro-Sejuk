<?php

use App\Enums\DocumentStatus;
use App\Enums\VerificationStatus;
use App\Models\TechnicianProfile;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::customer'), Title('Menunggu verifikasi')] class extends Component {
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

    /**
     * The three verification steps and whether each is finished.
     *
     * @return list<array{title: string, detail: string, done: bool}>
     */
    #[Computed]
    public function steps(): array
    {
        $documents = $this->profile?->documents ?? collect();
        $ktpDone = $documents->contains(fn ($document) => $document->type->value === 'ktp' && $document->status === DocumentStatus::Approved);
        $competencyReviewed = $documents->contains(fn ($document) => $document->type->value === 'sertifikat' && $document->status !== DocumentStatus::Pending);

        return [
            ['title' => 'Identitas', 'detail' => 'KTP diperiksa', 'done' => $ktpDone],
            ['title' => 'Kompetensi', 'detail' => 'Bukti ditinjau', 'done' => $competencyReviewed],
            ['title' => 'Aktivasi', 'detail' => 'Profil dipublikasikan', 'done' => false],
        ];
    }
}; ?>

@php
    $rejected = $this->profile?->verification_status === VerificationStatus::Rejected;
@endphp

<div class="mx-auto flex min-h-[calc(100vh-8rem)] max-w-2xl items-center px-4 py-10 sm:px-6 sm:py-12">
    <x-card class="w-full p-6 text-center sm:p-8">
        @if ($rejected)
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-coral-soft text-coral">
                <flux:icon.circle-alert class="size-8" />
            </span>
            <p class="mt-5 text-xs font-bold tracking-wider text-coral uppercase">Verifikasi ditolak</p>
            <h1 class="mt-2 text-2xl font-extrabold sm:text-3xl">Data akun belum bisa disetujui</h1>
            <p class="mx-auto mt-3 max-w-lg leading-relaxed text-ink-soft">
                {{ $this->profile->verification_note ?: 'Tim Sejuk belum bisa memverifikasi data yang kamu kirim.' }}
            </p>
            <p class="mt-3 text-sm text-mute">Balas email dari tim Sejuk atau hubungi kami untuk mengirim ulang dokumen.</p>
        @else
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-sun-soft text-sun-ink">
                <flux:icon.shield-check class="size-8" />
            </span>
            <p class="mt-5 text-xs font-bold tracking-wider text-sun-ink uppercase">Menunggu verifikasi</p>
            <h1 class="mt-2 text-2xl font-extrabold sm:text-3xl">Data akun berhasil dikirim</h1>
            <p class="mx-auto mt-3 max-w-lg leading-relaxed text-ink-soft">Tim Sejuk akan mencocokkan data KTP dan memeriksa sertifikat atau pengalaman kerja. Akun baru dapat menerima pesanan setelah proses ini selesai.</p>

            <div class="mt-7 grid gap-3 text-left sm:grid-cols-3">
                @foreach ($this->steps as $index => $step)
                    <div wire:key="step-{{ $index }}" class="rounded-2xl bg-paper p-4">
                        <span @class(['grid size-7 place-items-center rounded-full text-xs font-bold ring-1', 'bg-leaf text-white ring-leaf' => $step['done'], 'bg-white text-ice ring-line' => ! $step['done']])>
                            @if ($step['done']) <flux:icon.check class="size-3.5" /> @else {{ $index + 1 }} @endif
                        </span>
                        <p class="mt-3 font-bold">{{ $step['title'] }}</p>
                        <p class="mt-1 text-xs text-mute">{{ $step['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <x-button variant="secondary" :href="route('home')">Ke situs pelanggan</x-button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-button type="submit" variant="ghost" icon="log-out">Keluar</x-button>
            </form>
        </div>
    </x-card>
</div>
