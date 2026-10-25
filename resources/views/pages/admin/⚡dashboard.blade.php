<?php

use App\Enums\VerificationStatus;
use App\Models\Order;
use App\Models\TechnicianProfile;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin'), Title('Ringkasan')] class extends Component {
    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'Teknisi aktif' => TechnicianProfile::published()->count(),
            'Menunggu verifikasi' => TechnicianProfile::where('verification_status', VerificationStatus::Pending)->count(),
            'Pesanan aktif' => Order::active()->count(),
        ];
    }
}; ?>

<div class="space-y-6">
    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Ringkasan</h1>

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($this->stats as $label => $value)
            <x-card wire:key="stat-{{ $loop->index }}">
                <p class="text-sm text-mute">{{ $label }}</p>
                <p class="mt-2 text-3xl font-extrabold tnum">{{ $value }}</p>
            </x-card>
        @endforeach
    </div>
</div>
