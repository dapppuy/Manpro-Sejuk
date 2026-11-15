<?php

use App\Models\CustomerUnit;
use App\Models\UnitIssueReport;
use App\Models\UnitServiceLog;
use App\Notifications\AcUnitNotice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What anyone sees after scanning an AC's QR sticker: the unit's service history (no photos or owner details),
 * plus a form for tenants to report a problem to the owner.
 */
new #[Layout('layouts::customer'), Title('Riwayat AC')] class extends Component {
    #[Locked]
    public CustomerUnit $unit;

    public string $reporter_name = '';

    public string $reporter_contact = '';

    public string $issue_description = '';

    public bool $reported = false;

    public function mount(string $qrToken): void
    {
        $this->unit = CustomerUnit::where('qr_token', $qrToken)->firstOrFail();

        if (auth()->check()) {
            $this->reporter_name = auth()->user()->name;
            $this->reporter_contact = (string) auth()->user()->phone;
        }
    }

    /**
     * @return Collection<int, UnitServiceLog>
     */
    #[Computed]
    public function history(): Collection
    {
        return $this->unit->completedServiceLogs()->with('technician.technicianProfile')->limit(30)->get();
    }

    public function isOwner(): bool
    {
        return auth()->id() === $this->unit->user_id;
    }

    public function report(): void
    {
        $validated = $this->validate([
            'reporter_name' => ['required', 'string', 'max:80'],
            'reporter_contact' => ['nullable', 'string', 'max:80'],
            'issue_description' => ['required', 'string', 'min:10', 'max:1000'],
        ], attributes: ['reporter_name' => 'nama', 'reporter_contact' => 'kontak', 'issue_description' => 'keluhan']);

        $key = 'unit-report:'.$this->unit->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('issue_description', 'Terlalu banyak laporan dari perangkat ini. Coba lagi nanti.');

            return;
        }

        RateLimiter::hit($key, 3600);

        $this->unit->issueReports()->create([
            ...$validated,
            'reporter_contact' => $validated['reporter_contact'] ?: null,
            'reported_by_user_id' => auth()->id(),
            'status' => UnitIssueReport::STATUS_WAITING,
        ]);

        $this->unit->user->notify(new AcUnitNotice(
            $this->unit,
            'Laporan AC: '.$this->unit->name,
            $validated['reporter_name'].($this->unit->room_label ? ' ('.$this->unit->room_label.')' : '').' melaporkan: '.str($validated['issue_description'])->limit(140),
        ));

        $this->reset('issue_description');
        $this->reported = true;
    }
}; ?>

@php
    $unit = $this->unit;
    $latest = $this->history->first();
@endphp

<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10">
    <div class="flex items-start gap-4">
        <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-frost text-ice">
            <flux:icon.snowflake class="size-7" />
        </span>
        <div class="min-w-0">
            <p class="text-sm text-mute">Riwayat AC · Sejuk</p>
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $unit->name }}</h1>
            <p class="mt-1 text-ink-soft">{{ collect([$unit->room_label, $unit->specLine()])->filter()->implode(' · ') }}</p>
        </div>
    </div>

    @if ($this->isOwner())
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-frost p-4 text-sm">
            <p class="text-ice-deep">Ini unit AC milikmu.</p>
            <x-button size="sm" :href="route('units.show', $unit)" wire:navigate>Kelola unit</x-button>
        </div>
    @endif

    <dl class="mt-6 grid grid-cols-2 gap-3">
        <div class="rounded-2xl bg-white p-4 ring-1 ring-line">
            <dt class="text-xs text-mute">Servis terakhir</dt>
            <dd class="mt-1 font-bold">{{ $latest?->service_date->translatedFormat('j M Y') ?? 'Belum tercatat' }}</dd>
        </div>
        <div class="rounded-2xl bg-white p-4 ring-1 ring-line">
            <dt class="text-xs text-mute">Servis berikutnya</dt>
            <dd class="mt-1 font-bold">{{ $latest?->next_recommended_service_date?->translatedFormat('j M Y') ?? '-' }}</dd>
        </div>
    </dl>

    <h2 class="mt-8 text-lg font-extrabold">Riwayat servis</h2>
    <ol class="mt-4 space-y-3">
        @forelse ($this->history as $log)
            <li wire:key="log-{{ $log->id }}" class="rounded-2xl bg-white p-4 ring-1 ring-line">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-bold">{{ $log->service_type }}</p>
                    <p class="text-sm text-mute">{{ $log->service_date->translatedFormat('j F Y') }}</p>
                </div>
                <p class="text-sm text-ink-soft">{{ $log->technician?->technicianProfile?->business_name ?? 'Teknisi' }}</p>
                @if ($log->inspection_findings)
                    <p class="mt-2 text-sm"><span class="font-semibold">Temuan:</span> {{ $log->inspection_findings }}</p>
                @endif
                <p class="mt-1 text-sm"><span class="font-semibold">Tindakan:</span> {{ $log->actions_taken }}</p>
            </li>
        @empty
            <li class="rounded-2xl bg-white px-4 py-6 text-center text-sm text-mute ring-1 ring-line">Belum ada servis yang tercatat untuk unit ini.</li>
        @endforelse
    </ol>

    @unless ($this->isOwner())
        <x-card class="mt-8 p-5 sm:p-6">
            <x-section-title icon="message-square-text" title="AC ini bermasalah?" description="Laporanmu dikirim ke pemilik atau pengelola unit. Mereka yang memutuskan pemesanan teknisi." />

            @if ($reported)
                <div class="mt-5 flex items-start gap-3 rounded-2xl bg-leaf-soft p-4 text-sm text-leaf">
                    <flux:icon.circle-check class="mt-0.5 size-5 shrink-0" />
                    <p>Laporan terkirim. Pemilik sudah mendapat notifikasi.</p>
                </div>
            @endif

            <form wire:submit="report" class="mt-5 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input label="Nama" name="reporter_name" wire:model="reporter_name" required />
                    <x-form.input label="No. HP / kamar (opsional)" name="reporter_contact" wire:model="reporter_contact" />
                </div>
                <x-form.textarea label="Keluhan" name="issue_description" rows="3" wire:model="issue_description" placeholder="Contoh: AC menetes air sejak kemarin, angin tidak dingin." required />
                <x-button type="submit" wire:loading.attr="disabled" wire:target="report">Kirim laporan</x-button>
            </form>
        </x-card>
    @endunless
</div>
