<?php

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Services\OrderWorkflow;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::mitra'), Title('Input Biaya Final')] class extends Component {
    public Order $order;

    /**
     * @var list<array{label: string, amount: int|string|null, is_additional: bool}>
     */
    public array $items = [];

    public function mount(Order $order): void
    {
        Gate::authorize('respond', $order);

        if (! in_array($order->status, [OrderStatus::Terjadwal, OrderStatus::MenungguPersetujuanBiaya], true)) {
            $this->redirectRoute('mitra.orders.show', $order);

            return;
        }

        $this->order = $order->load(['service', 'costItems']);

        $existing = $order->costItems->reject(fn ($item) => $order->warranty && $item->amount === $order->warranty_fee && str_starts_with($item->label, 'Garansi Servis'));

        $this->items = $existing->isNotEmpty()
            ? $existing->map(fn ($item) => ['label' => $item->label, 'amount' => $item->amount, 'is_additional' => $item->is_additional])->values()->all()
            : [[
                'label' => $order->service->name.' · '.$order->ac_capacity->label(),
                'amount' => (int) (round((($order->estimate_min ?? 0) + ($order->estimate_max ?? 0)) / 2 / 5000) * 5000),
                'is_additional' => false,
            ]];
    }

    public function addItem(): void
    {
        $this->items[] = ['label' => '', 'amount' => null, 'is_additional' => true];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    #[Computed]
    public function total(): int
    {
        return (int) collect($this->items)->sum(fn (array $item) => (int) ($item['amount'] ?: 0)) + ($this->order->warranty ? $this->order->warranty_fee : 0);
    }

    public function submit(OrderWorkflow $workflow): void
    {
        Gate::authorize('respond', $this->order);

        $validated = $this->validate([
            'items' => ['required', 'array', 'min:1', 'max:15'],
            'items.*.label' => ['required', 'string', 'max:80'],
            'items.*.amount' => ['required', 'integer', 'min:0', 'max:50000000'],
            'items.*.is_additional' => ['boolean'],
        ], attributes: ['items.*.label' => 'nama biaya', 'items.*.amount' => 'nominal', 'items' => 'rincian biaya']);

        try {
            $workflow->submitFinalCost(
                $this->order,
                auth()->user()->technicianProfile,
                array_map(fn (array $item) => [
                    'label' => trim($item['label']),
                    'amount' => (int) $item['amount'],
                    'is_additional' => (bool) ($item['is_additional'] ?? false),
                ], $validated['items']),
            );
        } catch (InvalidOrderTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Biaya final berhasil dikirim untuk persetujuan pelanggan.');
        $this->redirectRoute('mitra.orders.show', $this->order, navigate: true);
    }
}; ?>

@php
    $inputClass = 'h-12 w-full rounded-xl bg-white px-4 text-sm ring-1 ring-inset ring-line outline-none placeholder:text-mute focus:ring-ice';
@endphp

<div>
    <a href="{{ route('mitra.orders.show', $order) }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke pesanan
    </a>
    <h1 class="mt-6 text-3xl font-extrabold">Input Biaya Final</h1>
    <p class="mt-2 text-ink-soft">Masukkan rincian harga pasti setelah pemeriksaan di lokasi. Pekerjaan baru dimulai setelah pelanggan menyetujui.</p>

    <x-card class="mt-7 p-5 sm:p-7">
        <form wire:submit="submit" class="space-y-6">
            <div class="rounded-2xl bg-frost px-4 py-3 text-sm text-ink-soft">
                Estimasi yang disetujui pelanggan: <span class="font-bold text-ink tnum">{{ rupiah($order->estimate_min) }} – {{ rupiah($order->estimate_max) }}</span>.
                Tandai <span class="font-semibold">Tambahan</span> untuk pekerjaan di luar permintaan awal.
            </div>

            <div class="space-y-3">
                @foreach ($items as $index => $item)
                    <div wire:key="item-{{ $index }}" @class(['grid gap-3 rounded-2xl p-4 sm:grid-cols-[1fr_170px_auto_auto] sm:items-center', 'bg-sun-soft/60' => $item['is_additional'], 'bg-paper' => ! $item['is_additional']])>
                        <div>
                            <input aria-label="Nama biaya {{ $index + 1 }}" wire:model="items.{{ $index }}.label" placeholder="Misal: Isi ulang freon R32" class="{{ $inputClass }}" />
                            @error("items.$index.label") <p class="mt-1 text-xs text-coral">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <div class="relative">
                                <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-sm text-mute">Rp</span>
                                <input aria-label="Nominal biaya {{ $index + 1 }}" type="number" min="0" step="1000" wire:model.live.debounce.300ms="items.{{ $index }}.amount" class="{{ $inputClass }} pl-10 tnum" />
                            </div>
                            @error("items.$index.amount") <p class="mt-1 text-xs text-coral">{{ $message }}</p> @enderror
                        </div>
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-sun-ink">
                            <input type="checkbox" wire:model.live="items.{{ $index }}.is_additional" class="size-4 accent-sun-line" /> Tambahan
                        </label>
                        <button type="button" wire:click="removeItem({{ $index }})" @disabled(count($items) === 1) class="grid size-12 place-items-center rounded-xl text-coral hover:bg-coral-soft disabled:opacity-30" aria-label="Hapus rincian">
                            <flux:icon.trash class="size-4" />
                        </button>
                    </div>
                @endforeach

                @if ($order->warranty)
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-dashed border-line px-4 py-3 text-sm">
                        <span class="text-ink-soft">Garansi Servis {{ config('sejuk.warranty.days') }} Hari <span class="text-mute">(dipilih pelanggan, otomatis)</span></span>
                        <span class="font-semibold tnum">{{ rupiah($order->warranty_fee) }}</span>
                    </div>
                @endif

                @error('items') <p class="text-xs text-coral">{{ $message }}</p> @enderror

                <x-button type="button" variant="secondary" icon="plus" wire:click="addItem">Tambah rincian</x-button>
            </div>

            <div class="flex items-center justify-between rounded-2xl bg-ink p-5 text-white">
                <span class="font-semibold text-white/70">Total biaya final</span>
                <span class="text-2xl font-extrabold tnum">{{ rupiah($this->total) }}</span>
            </div>

            <div class="border-t border-line pt-6">
                <x-button type="submit" size="lg" icon-trailing="arrow-right" wire:loading.attr="disabled">Kirim biaya final</x-button>
            </div>
        </form>
    </x-card>
</div>
