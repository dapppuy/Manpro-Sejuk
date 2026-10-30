<?php

use App\Enums\FundStatus;
use App\Enums\OrderStatus;
use App\Enums\VerificationStatus;
use App\Models\Order;
use App\Models\TechnicianProfile;
use App\Services\Kpi;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::admin'), Title('Ringkasan')] class extends Component {
    #[Url(as: 'hari')]
    public int $days = 30;

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function attention(): array
    {
        return [
            'Menunggu verifikasi' => TechnicianProfile::where('verification_status', VerificationStatus::Pending)->count(),
            'Sengketa' => Order::where('status', OrderStatus::Sengketa)->count(),
            'Pesanan aktif' => Order::active()->count(),
            'Siap dicairkan' => Order::where('fund_status', FundStatus::Diteruskan)->whereNull('payout_id')->distinct()->count('technician_id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function kpi(): array
    {
        $days = in_array($this->days, [7, 30, 90], true) ? $this->days : 30;

        return app(Kpi::class)->summarize(now()->subDays($days)->startOfDay(), now());
    }
}; ?>

@php
    $kpi = $this->kpi;
    $funnel = $kpi['funnel'];
    $top = max(1, max($funnel));
    $pct = fn ($value) => $value === null ? '–' : $value.'%';
    $links = ['Menunggu verifikasi' => route('admin.verifications.index'), 'Sengketa' => route('admin.orders.index', ['status' => 'sengketa']), 'Pesanan aktif' => route('admin.orders.index'), 'Siap dicairkan' => route('admin.funds')];
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Ringkasan</h1>
            <p class="mt-1 text-ink-soft">Yang perlu ditangani dan metrik produk.</p>
        </div>
        <div class="flex gap-2">
            @foreach ([7, 30, 90] as $option)
                <x-chip wire:key="d-{{ $option }}" :active="$days === $option" wire:click="$set('days', {{ $option }})">{{ $option }} hari</x-chip>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->attention as $label => $value)
            <a wire:key="att-{{ $loop->index }}" href="{{ $links[$label] }}" wire:navigate @class(['rounded-2xl p-5 ring-1 transition hover:ring-ink/20', 'bg-sun-soft ring-sun/40' => $value > 0 && $label !== 'Pesanan aktif', 'bg-white ring-line' => $value === 0 || $label === 'Pesanan aktif'])>
                <p class="text-sm text-mute">{{ $label }}</p>
                <p class="mt-2 text-3xl font-extrabold tnum">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    <x-card class="p-5 sm:p-6">
        <p class="font-bold">Konversi</p>
        <p class="mt-0.5 text-xs text-mute">Pencarian → permintaan → estimasi disetujui → dibayar → selesai, untuk pesanan {{ $days }} hari terakhir.</p>
        <div class="mt-5 space-y-3">
            @foreach ($funnel as $label => $value)
                <div wire:key="f-{{ $loop->index }}" class="grid grid-cols-[130px_1fr_60px] items-center gap-3 text-sm sm:grid-cols-[170px_1fr_70px]">
                    <span class="font-semibold">{{ $label }}</span>
                    <div class="h-3 overflow-hidden rounded-full bg-paper">
                        <div class="h-full rounded-full bg-ice" style="width: {{ round($value / $top * 100) }}%"></div>
                    </div>
                    <span class="text-right font-bold tnum">{{ $value }}</span>
                </div>
            @endforeach
        </div>
    </x-card>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['Rata-rata waktu respons teknisi', $kpi['avg_response_minutes'] === null ? '–' : $kpi['avg_response_minutes'].' mnt', 'Dari permintaan masuk sampai estimasi/penolakan'],
            ['Jatuh ke teknisi cadangan', $pct($kpi['backup_rate']), 'Pesanan yang sempat masuk tahap cadangan'],
            ['Estimasi ditolak', $pct($kpi['estimate_rejected_rate']), 'Dari estimasi yang sudah diputuskan pelanggan'],
            ['Biaya final ditolak', $pct($kpi['final_cost_rejected_rate']), 'Dari biaya final yang sudah diputuskan'],
            ['Selisih biaya final vs estimasi', $kpi['avg_over_estimate'] === null ? '–' : ($kpi['avg_over_estimate'] > 0 ? '+' : '').rupiah($kpi['avg_over_estimate']), 'Rata-rata terhadap batas atas estimasi, tanpa garansi'],
            ['Perbaikan ulang', $pct($kpi['rework_rate']), 'Dari pesanan selesai'],
            ['Pembayaran gagal / kedaluwarsa', $pct($kpi['payment_failed_rate']), 'Dari semua percobaan bayar yang berakhir'],
            ['Rating rata-rata', $kpi['avg_rating'] === null ? '–' : number_format($kpi['avg_rating'], 1, ',', '').' / 5', 'Ulasan yang tampil'],
            ['Pelanggan pesan lagi', $pct($kpi['repeat_rate']), 'Pelanggan dengan lebih dari 1 pesanan selesai'],
            ['Ambil garansi 30 hari', $pct($kpi['warranty_rate']), 'Dari semua permintaan'],
        ] as [$label, $value, $hint])
            <x-card wire:key="k-{{ $loop->index }}" class="p-5">
                <p class="text-sm text-mute">{{ $label }}</p>
                <p class="mt-2 text-2xl font-extrabold tnum">{{ $value }}</p>
                <p class="mt-1 text-xs text-ink-soft">{{ $hint }}</p>
            </x-card>
        @endforeach
    </div>
</div>
