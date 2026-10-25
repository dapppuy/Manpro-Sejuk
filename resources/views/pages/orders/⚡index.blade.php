<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::customer'), Title('Pesanan saya')] class extends Component {
    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function orders(): Collection
    {
        return auth()->user()->orders()->with(['service', 'technician.user'])->latest()->get();
    }
}; ?>

@php
    [$active, $history] = $this->orders->partition(fn (Order $order) => $order->status->isActive());
@endphp

<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-10">
    <h1 class="text-3xl font-extrabold tracking-tight">Pesanan saya</h1>
    <p class="mt-1 text-ink-soft">Pantau pesanan yang sedang berjalan dan lihat riwayat servismu.</p>

    @foreach (['Sedang berjalan' => $active, 'Riwayat' => $history] as $heading => $orders)
        <section class="mt-8">
            <h2 class="text-xs font-bold tracking-wider text-mute uppercase">{{ $heading }} <span class="tnum">({{ $orders->count() }})</span></h2>
            <div class="mt-3 space-y-3">
                @forelse ($orders as $order)
                    <a wire:key="order-{{ $order->id }}" href="{{ route('orders.show', $order) }}" wire:navigate class="flex flex-wrap items-center gap-4 rounded-2xl bg-white p-4 ring-1 ring-line transition hover:ring-ink/20 sm:p-5">
                        <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-frost text-ice">
                            <flux:icon :icon="$order->service->icon()" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">{{ $order->service->name }} <span class="font-medium text-mute tnum">· #{{ $order->code }}</span></p>
                            <p class="truncate text-sm text-ink-soft">
                                {{ $order->technician?->business_name ?? 'Belum ada teknisi' }} · {{ ($order->scheduled_date ?? $order->preferred_date)->translatedFormat('j M Y') }}
                            </p>
                        </div>
                        <x-order.status-pill :status="$order->status" class="!text-xs" />
                        <flux:icon.chevron-right class="hidden size-4 text-mute sm:block" />
                    </a>
                @empty
                    <div class="rounded-2xl bg-white px-5 py-8 text-center text-sm text-mute ring-1 ring-line">
                        @if ($heading === 'Sedang berjalan')
                            Belum ada pesanan aktif. <a href="{{ route('search') }}" wire:navigate class="font-semibold text-ice">Cari teknisi</a>
                        @else
                            Belum ada riwayat pesanan.
                        @endif
                    </div>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
