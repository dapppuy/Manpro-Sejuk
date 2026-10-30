<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Pesanan')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'aktif';

    #[Url(as: 'q')]
    public string $search = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return Order::query()
            ->with(['customer', 'technician', 'service'])
            ->when($this->status === 'aktif', fn ($query) => $query->active())
            ->when(OrderStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('code', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%"))
                ->orWhereHas('technician', fn ($technician) => $technician->where('business_name', 'like', "%{$term}%"))))
            ->latest()
            ->paginate(20);
    }
}; ?>

@php
    $filters = ['aktif' => 'Aktif', 'semua' => 'Semua', OrderStatus::Sengketa->value => 'Sengketa', OrderStatus::MenungguPembayaran->value => 'Menunggu bayar', OrderStatus::Selesai->value => 'Selesai', OrderStatus::Dibatalkan->value => 'Dibatalkan'];
@endphp

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Pesanan</h1>
            <p class="mt-1 text-ink-soft">Semua pesanan beserta riwayat statusnya.</p>
        </div>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Kode, pelanggan, atau teknisi" class="h-11 w-full rounded-full bg-white px-4 text-sm ring-1 ring-line outline-none focus:ring-ice sm:w-72" />
    </div>

    <div class="no-scrollbar mt-6 flex gap-2 overflow-x-auto">
        @foreach ($filters as $value => $label)
            <x-chip wire:key="f-{{ $value }}" :active="$status === $value" wire:click="$set('status', '{{ $value }}')">{{ $label }}</x-chip>
        @endforeach
    </div>

    <x-card class="mt-6 overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-paper text-xs tracking-wider text-mute uppercase">
                <tr>
                    <th class="px-5 py-3 font-semibold">Pesanan</th>
                    <th class="px-5 py-3 font-semibold">Pelanggan</th>
                    <th class="px-5 py-3 font-semibold">Teknisi</th>
                    <th class="px-5 py-3 text-right font-semibold">Total</th>
                    <th class="px-5 py-3 text-right font-semibold">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->orders as $order)
                    <tr wire:key="o-{{ $order->id }}" class="border-t border-line hover:bg-paper">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="font-semibold hover:text-ice tnum">#{{ $order->code }}</a>
                            <p class="text-xs text-mute">{{ $order->service->name }} · {{ $order->created_at?->translatedFormat('j M, H.i') }}</p>
                        </td>
                        <td class="px-5 py-3.5">{{ $order->customer->name }}</td>
                        <td class="px-5 py-3.5">{{ $order->technician?->business_name ?? '–' }}</td>
                        <td class="px-5 py-3.5 text-right tnum">{{ $order->final_total ? rupiah($order->final_total) : '–' }}</td>
                        <td class="px-5 py-3.5 text-right"><x-order.status-pill :status="$order->status" class="!text-xs" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-mute">Tidak ada pesanan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $this->orders->links() }}</div>
</div>
