<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TechnicianProfile;
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

    <x-card class="mt-7 p-6">
        <p class="font-bold">Lengkapi profilmu</p>
        <p class="mt-1 text-sm text-ink-soft">Profil yang lengkap dengan harga acuan, merek, dan bio lebih sering dipilih pelanggan.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <x-button variant="secondary" size="sm" icon="user-round" :href="route('mitra.profile')" wire:navigate>Profil</x-button>
            <x-button variant="secondary" size="sm" icon="tag" :href="route('mitra.prices')" wire:navigate>Harga acuan</x-button>
        </div>
    </x-card>
</div>
