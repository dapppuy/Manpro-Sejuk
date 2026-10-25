<?php

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

    public function toggleAccepting(): void
    {
        $this->profile->update(['is_accepting_orders' => ! $this->profile->is_accepting_orders]);
    }

    public function greeting(): string
    {
        return match (true) {
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

<div>
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

    <x-card class="mt-7 p-6">
        <p class="font-bold">Lengkapi profilmu</p>
        <p class="mt-1 text-sm text-ink-soft">Profil yang lengkap dengan harga acuan, merek, dan bio lebih sering dipilih pelanggan.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <x-button variant="secondary" size="sm" icon="user-round" :href="route('mitra.profile')" wire:navigate>Profil</x-button>
            <x-button variant="secondary" size="sm" icon="tag" :href="route('mitra.prices')" wire:navigate>Harga acuan</x-button>
        </div>
    </x-card>
</div>
