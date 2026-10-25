<?php

use App\Models\TechnicianProfile;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::mitra'), Title('Dashboard')] class extends Component {
    #[Computed]
    public function profile(): ?TechnicianProfile
    {
        return auth()->user()->technicianProfile;
    }

    public function toggleAccepting(): void
    {
        $profile = $this->profile;

        abort_if($profile === null, 403);

        $profile->update(['is_accepting_orders' => ! $profile->is_accepting_orders]);
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-mute">{{ now()->translatedFormat('l, j F Y') }}</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Halo, {{ str(auth()->user()->name)->before(' ') }} 👋</h1>
        </div>

        @if ($this->profile)
            <button type="button" wire:click="toggleAccepting" @class([
                'inline-flex items-center gap-3 rounded-full py-2 pr-4 pl-2 text-sm font-semibold ring-1 transition',
                'bg-leaf-soft text-leaf ring-leaf/30' => $this->profile->is_accepting_orders,
                'bg-white text-mute ring-line' => ! $this->profile->is_accepting_orders,
            ])>
                <span @class(['relative h-6 w-10 rounded-full transition', 'bg-leaf' => $this->profile->is_accepting_orders, 'bg-line' => ! $this->profile->is_accepting_orders])>
                    <span @class(['absolute top-1 size-4 rounded-full bg-white transition-all', 'left-5' => $this->profile->is_accepting_orders, 'left-1' => ! $this->profile->is_accepting_orders])></span>
                </span>
                {{ $this->profile->is_accepting_orders ? 'Siap menerima pesanan' : 'Tidak menerima pesanan' }}
            </button>
        @endif
    </div>

    <x-card>
        <p class="text-sm text-mute">Statistik, pesanan masuk, dan menu lain akan muncul di sini.</p>
    </x-card>
</div>
