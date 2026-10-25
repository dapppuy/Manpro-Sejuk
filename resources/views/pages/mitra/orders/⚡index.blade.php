<?php

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TechnicianProfile;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::mitra'), Title('Pesanan')] class extends Component {
    #[Url]
    public string $tab = 'masuk';

    #[Computed]
    public function profile(): TechnicianProfile
    {
        return auth()->user()->technicianProfile;
    }

    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function incoming(): Collection
    {
        return $this->profile->orders()
            ->where('status', OrderStatus::MenungguRespons)
            ->with(['service', 'customer'])
            ->orderBy('response_deadline')
            ->get();
    }

    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function active(): Collection
    {
        return $this->profile->orders()
            ->whereIn('status', array_values(array_filter(OrderStatus::active(), fn (OrderStatus $status) => ! in_array($status, [OrderStatus::MenungguRespons, OrderStatus::CariCadangan], true))))
            ->with(['service', 'customer'])
            ->orderBy('scheduled_date')
            ->get();
    }

    /**
     * Finished or cancelled orders, plus orders this technician declined or let lapse.
     *
     * @return Collection<int, Order>
     */
    #[Computed]
    public function history(): Collection
    {
        $profileId = $this->profile->id;

        return Order::query()
            ->whereHas('offers', fn ($offers) => $offers->where('technician_id', $profileId))
            ->where(fn ($query) => $query
                ->whereIn('status', [OrderStatus::Selesai, OrderStatus::Dibatalkan])
                ->orWhere('technician_id', '!=', $profileId)
                ->orWhereNull('technician_id'))
            ->with(['service', 'customer', 'offers' => fn ($offers) => $offers->where('technician_id', $profileId)])
            ->latest()
            ->limit(50)
            ->get();
    }
}; ?>

@php
    $tabs = ['masuk' => ['Masuk', $this->incoming], 'aktif' => ['Aktif', $this->active], 'riwayat' => ['Riwayat', $this->history]];
    $orders = $tabs[$tab][1] ?? $this->incoming;
@endphp

<div wire:poll.15s>
    <p class="text-sm text-mute">Sejuk Mitra</p>
    <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Pesanan</h1>

    <div class="no-scrollbar mt-6 flex gap-2 overflow-x-auto">
        @foreach ($tabs as $key => [$label, $list])
            <x-chip wire:key="tab-{{ $key }}" :active="$tab === $key" wire:click="$set('tab', '{{ $key }}')">
                {{ $label }} <span class="ml-1 tnum opacity-70">{{ $list->count() }}</span>
            </x-chip>
        @endforeach
    </div>

    <div class="mt-6 space-y-3">
        @forelse ($orders as $order)
            @php
                $myOffer = $order->relationLoaded('offers') ? $order->offers->first() : null;
                $lost = $myOffer && in_array($myOffer->status, [OfferStatus::Ditolak, OfferStatus::Kedaluwarsa], true) && $order->technician_id !== $this->profile->id;
            @endphp
            <a wire:key="order-{{ $order->id }}" href="{{ route('mitra.orders.show', $order) }}" wire:navigate class="flex flex-wrap items-center gap-4 rounded-2xl bg-white p-4 ring-1 ring-line transition hover:ring-ink/20 sm:p-5">
                <x-avatar :name="$order->customer->name" class="!size-12 rounded-2xl" />
                <div class="min-w-0 flex-1">
                    <p class="font-bold">{{ $order->service->name }} · {{ $order->brandLabel() }} {{ $order->ac_capacity->label() }}</p>
                    <p class="truncate text-sm text-ink-soft">{{ $order->address }}</p>
                    <p class="mt-0.5 text-xs text-mute tnum">#{{ $order->code }} · {{ ($order->scheduled_date ?? $order->preferred_date)->translatedFormat('D, j M') }}, {{ ($order->scheduled_window ?? $order->time_window)->range() }}</p>
                </div>
                @if ($tab === 'masuk')
                    <span class="rounded-full bg-sun-soft px-3 py-1 text-xs font-bold text-sun-ink tnum"
                        x-data="{ left: {{ $order->secondsToRespond() }} }" x-init="setInterval(() => left = Math.max(0, left - 1), 1000)"
                        x-text="'Respons ' + String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0')">Respons</span>
                @elseif ($lost)
                    <x-pill>{{ $myOffer->status->label() }}</x-pill>
                @else
                    <x-order.status-pill :status="$order->status" class="!text-xs" />
                @endif
            </a>
        @empty
            <x-card class="px-6 py-12 text-center">
                <flux:icon.clipboard-list class="mx-auto size-8 text-mute" />
                <p class="mt-3 font-bold">{{ match ($tab) { 'aktif' => 'Belum ada pesanan aktif', 'riwayat' => 'Belum ada riwayat', default => 'Belum ada pesanan masuk' } }}</p>
                @if ($tab === 'masuk' && ! $this->profile->is_accepting_orders)
                    <p class="mt-1 text-sm text-mute">Statusmu sedang tidak menerima pesanan. Aktifkan di dashboard.</p>
                @endif
            </x-card>
        @endforelse
    </div>
</div>
