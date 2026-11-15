<?php

use App\Models\CustomerUnit;
use App\Models\UnitIssueReport;
use App\Models\UnitServiceLog;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::customer'), Title('Riwayat AC')] class extends Component {
    #[Locked]
    public CustomerUnit $unit;

    public function mount(CustomerUnit $unit): void
    {
        abort_unless($unit->user_id === auth()->id(), 404);

        $this->unit = $unit;
    }

    /**
     * @return Collection<int, UnitServiceLog>
     */
    #[Computed]
    public function history(): Collection
    {
        return $this->unit->completedServiceLogs()->with(['technician.technicianProfile', 'order.service'])->get();
    }

    /**
     * @return Collection<int, UnitIssueReport>
     */
    #[Computed]
    public function reports(): Collection
    {
        return $this->unit->issueReports()->limit(20)->get();
    }

    /**
     * Start an order for this unit: the request form picks it up after the customer chooses a technician.
     */
    public function bookTechnician(): void
    {
        session(['sejuk.order_unit' => $this->unit->id]);

        $this->redirectRoute('search', navigate: true);
    }

    public function setReportStatus(int $reportId, string $status): void
    {
        abort_unless(in_array($status, [UnitIssueReport::STATUS_DONE, UnitIssueReport::STATUS_DECLINED], true), 422);

        $this->unit->issueReports()->findOrFail($reportId)->update(['status' => $status]);
        unset($this->reports);

        Flux::toast(text: 'Status laporan diperbarui.');
    }
}; ?>

@php
    $unit = $this->unit;
    $latest = $this->history->first();
@endphp

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
    <style>
        @media print {
            body * { visibility: hidden; }
            #qr-sticker, #qr-sticker * { visibility: visible; }
            #qr-sticker { position: absolute; top: 0; left: 0; box-shadow: none; }
        }
    </style>

    <a href="{{ route('units.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Unit AC saya
    </a>

    <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight">{{ $unit->name }}</h1>
            <p class="mt-1 text-ink-soft">{{ collect([$unit->room_label, $unit->specLine()])->filter()->implode(' · ') ?: 'Detail unit belum diisi' }}</p>
        </div>
        <x-button icon="search" wire:click="bookTechnician">Pesan teknisi untuk unit ini</x-button>
    </div>

    <div class="mt-7 grid gap-6 lg:grid-cols-[320px_1fr]">
        <div class="space-y-5">
            <x-card id="qr-sticker" class="p-5 text-center">
                <p class="text-xs font-bold tracking-wider text-mute uppercase">Stiker Riwayat AC</p>
                <div class="mx-auto mt-3 w-fit rounded-2xl bg-white p-2 ring-1 ring-line">{!! $unit->qrSvg(200) !!}</div>
                <p class="mt-3 font-extrabold">{{ $unit->name }}</p>
                @if ($unit->room_label)
                    <p class="text-sm text-ink-soft">{{ $unit->room_label }}</p>
                @endif
                <p class="mt-2 text-xs text-mute">Scan untuk melihat riwayat servis{{ $unit->is_kos_unit ? ' atau melaporkan masalah' : '' }} · Sejuk</p>
            </x-card>
            <div class="flex flex-wrap gap-2">
                <x-button size="sm" variant="secondary" icon="file-text" onclick="window.print()">Cetak stiker</x-button>
                <x-button size="sm" variant="secondary" icon="external-link" :href="$unit->qr_url" target="_blank">Buka halaman QR</x-button>
            </div>
            <p class="text-xs text-mute">Tempel stiker di badan unit indoor. Teknisi akan memindainya saat servis, dan riwayatnya ikut tercatat di sini.</p>

            <x-card class="p-5">
                <p class="text-xs font-bold tracking-wider text-mute uppercase">Data unit</p>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach (['Merek' => $unit->brand, 'Jenis' => $unit->typeLabel(), 'Nomor seri' => $unit->serial_number, 'Catatan' => $unit->notes] as $label => $value)
                        <div wire:key="spec-{{ $loop->index }}" class="flex justify-between gap-4">
                            <dt class="shrink-0 text-mute">{{ $label }}</dt>
                            <dd class="text-right font-medium">{{ $value ?: '-' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($unit->sticker_photo_path)
                    <a href="{{ route('units.photo', [$unit, 'stiker']) }}" target="_blank" class="mt-4 block overflow-hidden rounded-xl ring-1 ring-line">
                        <img src="{{ route('units.photo', [$unit, 'stiker']) }}" alt="Foto stiker spesifikasi {{ $unit->name }}" class="h-36 w-full object-cover" />
                    </a>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card class="p-5 sm:p-6">
                <x-section-title icon="clipboard-list" title="Riwayat servis" description="Dicatat teknisi setiap kali unit ini diservis lewat Sejuk." />

                @if ($latest?->next_recommended_service_date)
                    <div @class([
                        'mt-5 flex items-center gap-3 rounded-2xl p-4 text-sm',
                        'bg-sun-soft text-sun-ink' => $latest->next_recommended_service_date->isPast(),
                        'bg-frost text-ice-deep' => ! $latest->next_recommended_service_date->isPast(),
                    ])>
                        <flux:icon.calendar-clock class="size-5 shrink-0" />
                        <p>Servis berikutnya disarankan mulai <span class="font-bold">{{ $latest->next_recommended_service_date->translatedFormat('j F Y') }}</span>. Kami kirim pengingat ke emailmu saat waktunya tiba.</p>
                    </div>
                @endif

                <ol class="mt-6 space-y-4">
                    @forelse ($this->history as $log)
                        <li wire:key="log-{{ $log->id }}" class="rounded-2xl p-4 ring-1 ring-line">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="font-bold">{{ $log->service_type }}</p>
                                <p class="text-sm text-mute">{{ $log->service_date->translatedFormat('j F Y') }}</p>
                            </div>
                            <p class="text-sm text-ink-soft">{{ $log->technician?->technicianProfile?->business_name ?? 'Teknisi' }}@if ($log->order) · Pesanan #{{ $log->order->code }}@endif</p>
                            @if ($log->inspection_findings)
                                <p class="mt-3 text-sm"><span class="font-semibold">Temuan:</span> {{ $log->inspection_findings }}</p>
                            @endif
                            <p class="mt-1 text-sm"><span class="font-semibold">Tindakan:</span> {{ $log->actions_taken }}</p>
                            @if ($log->before_photo_path || $log->after_photo_path)
                                <div class="mt-3 flex gap-2">
                                    @foreach (['sebelum' => $log->before_photo_path, 'sesudah' => $log->after_photo_path] as $side => $path)
                                        @continue($path === null)
                                        <a wire:key="photo-{{ $log->id }}-{{ $side }}" href="{{ route('units.photo', [$unit, $log->id.'-'.$side]) }}" target="_blank" class="relative size-20 overflow-hidden rounded-xl ring-1 ring-line">
                                            <img src="{{ route('units.photo', [$unit, $log->id.'-'.$side]) }}" alt="Foto {{ $side }} servis" class="size-full object-cover" loading="lazy" />
                                            <span class="absolute bottom-1 left-1 rounded-full bg-ink/70 px-1.5 text-[10px] font-bold text-white capitalize">{{ $side }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="rounded-2xl bg-paper px-4 py-6 text-center text-sm text-mute">Belum ada riwayat. Riwayat tercatat otomatis setelah pesanan untuk unit ini selesai.</li>
                    @endforelse
                </ol>
            </x-card>

            <x-card class="p-5 sm:p-6">
                <x-section-title icon="message-square-text" title="Laporan dari penghuni" description="Masuk saat seseorang memindai QR dan melaporkan masalah AC ini." />
                <div class="mt-5 space-y-3">
                    @forelse ($this->reports as $report)
                        <div wire:key="report-{{ $report->id }}" class="rounded-2xl p-4 ring-1 ring-line">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold">{{ $report->reporter_name }}@if ($report->reporter_contact) <span class="font-normal text-mute">· {{ $report->reporter_contact }}</span>@endif</p>
                                <span @class([
                                    'rounded-full px-2.5 py-0.5 text-xs font-bold',
                                    'bg-sun-soft text-sun-ink' => $report->status === UnitIssueReport::STATUS_WAITING,
                                    'bg-frost text-ice-deep' => $report->status === UnitIssueReport::STATUS_ORDERED,
                                    'bg-leaf-soft text-leaf' => $report->status === UnitIssueReport::STATUS_DONE,
                                    'bg-ink/5 text-ink-soft' => $report->status === UnitIssueReport::STATUS_DECLINED,
                                ])>{{ UnitIssueReport::STATUS_LABELS[$report->status] ?? $report->status }}</span>
                            </div>
                            <p class="mt-2 text-sm text-ink-soft">{{ $report->issue_description }}</p>
                            <p class="mt-2 text-xs text-mute">{{ $report->created_at?->translatedFormat('j M Y, H.i') }}</p>
                            @if (in_array($report->status, [UnitIssueReport::STATUS_WAITING, UnitIssueReport::STATUS_ORDERED], true))
                                <div class="mt-3 flex flex-wrap gap-1">
                                    @if ($report->status === UnitIssueReport::STATUS_WAITING)
                                        <x-button size="sm" wire:click="bookTechnician">Pesan teknisi</x-button>
                                    @endif
                                    <x-button size="sm" variant="ghost" wire:click="setReportStatus({{ $report->id }}, '{{ UnitIssueReport::STATUS_DONE }}')">Tandai selesai</x-button>
                                    @if ($report->status === UnitIssueReport::STATUS_WAITING)
                                        <x-button size="sm" variant="ghost" wire:click="setReportStatus({{ $report->id }}, '{{ UnitIssueReport::STATUS_DECLINED }}')">Tolak</x-button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-2xl bg-paper px-4 py-5 text-sm text-mute">Belum ada laporan.</p>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
</div>
