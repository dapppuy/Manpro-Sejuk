<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TechnicianProfile;
use App\Services\TechnicianEarnings;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::mitra'), Title('Dashboard')] class extends Component {
    #[Computed]
    public function profile(): TechnicianProfile
    {
        return auth()->user()->technicianProfile;
    }

    #[Computed]
    public function latestIncoming(): ?Order
    {
        return $this->profile->orders()
            ->where('status', OrderStatus::MenungguRespons)
            ->where('response_deadline', '>', now())
            ->with(['service', 'customer'])
            ->orderBy('response_deadline')
            ->first();
    }

    /**
     * @return array{earnings: array{this_month: int, last_month: int, change_percent: int|null}, workload: array{active: int, needs_action: int}, completion: array{rate: int|null, completed: int, finished: int}}
     */
    #[Computed]
    public function stats(): array
    {
        $earnings = app(TechnicianEarnings::class);

        return [
            'earnings' => $earnings->monthlyComparison($this->profile),
            'workload' => $earnings->workload($this->profile),
            'completion' => $earnings->completionRate($this->profile),
        ];
    }

    #[Computed]
    public function profileIncomplete(): bool
    {
        return blank($this->profile->bio) || ! $this->profile->prices()->exists() || ! $this->profile->photos()->exists();
    }

    public function toggleAccepting(): void
    {
        $this->profile->update(['is_accepting_orders' => ! $this->profile->is_accepting_orders]);
    }

    public function greeting(): string
    {
        return match (true) {
            now()->hour < 4 => 'Selamat malam',
            now()->hour < 11 => 'Selamat pagi',
            now()->hour < 15 => 'Selamat siang',
            now()->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }
}; ?>

@php
    $accepting = $this->profile->is_accepting_orders;
    $stats = $this->stats;
    $change = $stats['earnings']['change_percent'];
@endphp

<div wire:poll.30s>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-mute">{{ $this->greeting() }}, {{ str(auth()->user()->name)->before(' ') }}</p>
            <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Dashboard Teknisi</h1>
        </div>
        <button type="button" wire:click="toggleAccepting" @class([
            'inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-bold transition',
            'bg-leaf-soft text-leaf' => $accepting,
            'bg-ink/5 text-ink-soft' => ! $accepting,
        ]) aria-pressed="{{ $accepting ? 'true' : 'false' }}" title="Klik untuk mengubah status">
            <span @class(['size-2 rounded-full', 'bg-leaf' => $accepting, 'bg-mute' => ! $accepting])></span>
            {{ $accepting ? 'Siap menerima pesanan' : 'Tidak menerima pesanan' }}
        </button>
    </div>

    <div class="mt-7 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['banknote', 'Pendapatan bulan ini', rupiah($stats['earnings']['this_month']), $change === null ? 'Belum ada data bulan lalu' : ($change >= 0 ? '+' : '').$change.'% dari bulan lalu', route('mitra.earnings')],
            ['briefcase-business', 'Pesanan aktif', $stats['workload']['active'].' pesanan', $stats['workload']['needs_action'] > 0 ? $stats['workload']['needs_action'].' perlu tindakan' : 'Tidak ada yang menunggu', route('mitra.orders.index', ['tab' => 'aktif'])],
            ['trending-up', 'Penyelesaian', $stats['completion']['rate'] === null ? '–' : $stats['completion']['rate'].'%', $stats['completion']['completed'].' dari '.$stats['completion']['finished'].' pesanan', route('mitra.orders.index', ['tab' => 'riwayat'])],
        ] as [$icon, $label, $value, $hint, $href])
            <a wire:key="stat-{{ $loop->index }}" href="{{ $href }}" wire:navigate class="rounded-2xl bg-white p-5 ring-1 ring-line transition hover:ring-ink/20">
                <span class="grid size-10 place-items-center rounded-xl bg-frost text-ice"><flux:icon :icon="$icon" class="size-5" /></span>
                <p class="mt-4 text-xs font-semibold text-mute">{{ $label }}</p>
                <p class="mt-1 text-xl font-extrabold tnum">{{ $value }}</p>
                <p @class(['mt-1 text-xs', 'text-leaf' => $loop->first && ($change ?? 0) > 0, 'text-coral' => $loop->first && ($change ?? 0) < 0, 'text-ink-soft' => ! $loop->first || ! $change])>{{ $hint }}</p>
            </a>
        @endforeach
    </div>

    @if ($this->latestIncoming)
        @php $incoming = $this->latestIncoming; @endphp
        <x-card class="mt-7 overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
                <div>
                    <p class="font-bold">Pesanan masuk terbaru</p>
                    <p class="mt-0.5 text-xs text-mute">Tanggapi sebelum diteruskan ke teknisi cadangan.</p>
                </div>
                <span class="rounded-full bg-coral-soft px-3 py-1 text-xs font-bold text-coral">Baru</span>
            </div>
            <div class="flex flex-wrap items-center gap-5 p-5 sm:p-6">
                <x-avatar :name="$incoming->customer->name" class="!size-12 rounded-2xl" />
                <div class="min-w-0 flex-1">
                    <p class="font-bold">{{ $incoming->service->name }} · {{ $incoming->brandLabel() }} {{ $incoming->ac_capacity->label() }}</p>
                    <p class="mt-1 text-sm text-ink-soft">{{ $incoming->address }}</p>
                </div>
                <x-button :href="route('mitra.orders.show', $incoming)" wire:navigate>Lihat pesanan</x-button>
            </div>
        </x-card>
    @endif

    @if ($this->profileIncomplete)
        <x-card class="mt-7 p-6">
            <p class="font-bold">Lengkapi profilmu</p>
            <p class="mt-1 text-sm text-ink-soft">Profil yang lengkap dengan bio, harga acuan, dan foto hasil kerja lebih sering dipilih pelanggan.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-button variant="secondary" size="sm" icon="user-round" :href="route('mitra.profile')" wire:navigate>Profil</x-button>
                <x-button variant="secondary" size="sm" icon="tag" :href="route('mitra.prices')" wire:navigate>Harga acuan</x-button>
                <x-button variant="secondary" size="sm" icon="images" :href="route('mitra.gallery')" wire:navigate>Galeri kerja</x-button>
            </div>
        </x-card>
    @endif
</div>
