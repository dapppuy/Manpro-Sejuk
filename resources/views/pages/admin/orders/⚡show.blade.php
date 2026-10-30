<?php

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Services\OrderWorkflow;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component {
    public Order $order;

    public string $reason = '';

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function cancel(OrderWorkflow $workflow): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:5', 'max:300']], attributes: ['reason' => 'alasan']);

        $this->run(fn () => $workflow->cancelByAdmin($this->order, auth()->user(), $this->reason));
    }

    public function resolve(bool $forwardToTechnician, OrderWorkflow $workflow): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:5', 'max:300']], attributes: ['reason' => 'catatan keputusan']);

        $this->run(fn () => $workflow->resolveDispute($this->order, auth()->user(), $forwardToTechnician, $this->reason));
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $this->order->load([
            'customer', 'technician.user', 'service', 'media', 'costItems', 'payments', 'review',
            'workProofs', 'offers.technician', 'statusLogs' => fn ($logs) => $logs->with('actor')->orderBy('id'),
        ]);

        return $this->view()->title('Pesanan #'.$this->order->code);
    }

    private function run(Closure $action): void
    {
        try {
            $action();
            $this->reset('reason');
            Flux::toast(variant: 'success', text: 'Pesanan diperbarui.');
        } catch (InvalidOrderTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }

        $this->order->refresh();
    }
}; ?>

@php
    $order = $this->order;
    $status = $order->status;
@endphp

<div>
    <a href="{{ route('admin.orders.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke pesanan
    </a>

    <div class="mt-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-mute tnum">Pesanan #{{ $order->code }} · dibuat {{ $order->created_at?->translatedFormat('j F Y, H.i') }}</p>
            <h1 class="mt-1 text-2xl font-extrabold sm:text-3xl">{{ $order->service->name }}</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($order->fund_status)
                <x-pill :tone="$order->fund_status->tone()">Dana: {{ $order->fund_status->label() }}</x-pill>
            @endif
            <x-order.status-pill :status="$status" />
        </div>
    </div>

    <div class="mt-7 grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            <x-card class="p-5 sm:p-6">
                <x-section-title icon="clipboard-list" title="Detail permintaan" />
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    @foreach (array_filter([
                        'Pelanggan' => $order->customer->name.' · '.($order->customer->phone ?? $order->customer->email),
                        'Teknisi' => $order->technician?->business_name,
                        'Unit AC' => $order->brandLabel().' · '.$order->ac_capacity->label(),
                        'Lokasi' => $order->fullAddress(),
                        'Jadwal' => ($order->scheduled_date ?? $order->preferred_date)->translatedFormat('l, j M').' · '.($order->scheduled_window ?? $order->time_window)->range(),
                        'Estimasi' => $order->estimate_min ? rupiah($order->estimate_min).' – '.rupiah($order->estimate_max) : null,
                        'Biaya final' => $order->final_total ? rupiah($order->final_total) : null,
                        'Komisi' => $order->commission_amount ? rupiah($order->commission_amount) : null,
                        'Perbaikan ulang' => $order->rework_count > 0 ? $order->rework_count.'×' : null,
                        'Alasan batal' => $order->cancelled_reason,
                    ]) as $label => $value)
                        <div wire:key="d-{{ $loop->index }}">
                            <dt class="text-xs text-mute">{{ $label }}</dt>
                            <dd class="mt-1 font-semibold break-words">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($order->notes)
                    <p class="mt-4 rounded-xl bg-paper px-3 py-2 text-sm text-ink-soft">“{{ $order->notes }}”</p>
                @endif
            </x-card>

            @if ($order->costItems->isNotEmpty())
                <x-card class="overflow-hidden">
                    <p class="border-b border-line px-5 py-4 font-bold">Rincian biaya final</p>
                    @foreach ($order->costItems as $item)
                        <div wire:key="ci-{{ $item->id }}" @class(['flex justify-between gap-4 border-b border-line px-5 py-3 text-sm last:border-b-0', 'bg-sun-soft/50' => $item->is_additional])>
                            <span>{{ $item->label }} @if ($item->is_additional)<span class="ml-1 rounded bg-sun px-1.5 py-0.5 text-[10px] font-bold uppercase">Baru</span>@endif</span>
                            <span class="font-semibold tnum">{{ rupiah($item->amount) }}</span>
                        </div>
                    @endforeach
                </x-card>
            @endif

            @if ($order->workProofs->isNotEmpty())
                <x-card class="p-5 sm:p-6">
                    <p class="font-bold">Bukti kerja</p>
                    @foreach ($order->workProofs as $proof)
                        <div wire:key="wp-{{ $proof->id }}" class="mt-4">
                            <p class="text-xs font-semibold text-mute">Putaran {{ $proof->round }} · {{ $proof->created_at?->translatedFormat('j M, H.i') }}</p>
                            <div class="mt-2 grid grid-cols-2 gap-3">
                                @foreach (['sebelum', 'sesudah'] as $side)
                                    <a wire:key="wp-{{ $proof->id }}-{{ $side }}" href="{{ $proof->photoUrl($side) }}" target="_blank"><img src="{{ $proof->photoUrl($side) }}" alt="Foto {{ $side }}" class="aspect-[4/3] w-full rounded-xl bg-paper object-cover" /></a>
                                @endforeach
                            </div>
                            @if ($proof->complaint)
                                <p class="mt-2 rounded-xl bg-coral-soft px-3 py-2 text-sm text-coral">Keluhan pelanggan: {{ $proof->complaint }}</p>
                            @endif
                        </div>
                    @endforeach
                </x-card>
            @endif

            <x-card class="p-5 sm:p-6">
                <p class="font-bold">Riwayat status</p>
                <ol class="mt-5 space-y-4 border-l-2 border-line pl-6">
                    @foreach ($order->statusLogs as $log)
                        <li wire:key="log-{{ $log->id }}" class="relative">
                            <span class="absolute top-1 -left-[31px] size-3 rounded-full bg-ice ring-4 ring-white"></span>
                            <p class="text-sm font-semibold">{{ $log->to_status->label() }}</p>
                            <p class="text-xs text-mute">
                                {{ $log->created_at->translatedFormat('j M Y, H.i') }} · {{ $log->actor?->name ?? 'Sistem' }}
                                @if ($log->note) · {{ $log->note }} @endif
                            </p>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-10 lg:self-start">
            <x-card class="p-5 text-sm">
                <p class="font-bold">Teknisi yang ditawari</p>
                <ul class="mt-3 space-y-2">
                    @foreach ($order->offers as $offer)
                        <li wire:key="of-{{ $offer->id }}" class="flex justify-between gap-3">
                            <span>{{ $offer->technician->business_name }}</span>
                            <span class="text-xs text-mute">{{ $offer->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            @if ($order->payments->isNotEmpty())
                <x-card class="p-5 text-sm">
                    <p class="font-bold">Pembayaran</p>
                    <ul class="mt-3 space-y-2">
                        @foreach ($order->payments as $payment)
                            <li wire:key="pay-{{ $payment->id }}" class="flex justify-between gap-3">
                                <span>{{ $payment->method->label() }} · <span class="tnum">{{ rupiah($payment->amount) }}</span></span>
                                <span class="text-xs text-mute">{{ $payment->status->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($status === OrderStatus::Sengketa)
                <x-card class="p-5">
                    <p class="font-bold">Selesaikan sengketa</p>
                    <p class="mt-1 text-sm text-ink-soft">AC masih bermasalah setelah {{ $order->rework_count }} kali perbaikan ulang. Hubungi kedua pihak, lalu putuskan.</p>
                    <x-form.textarea class="mt-3" label="Catatan keputusan" name="reason" rows="3" wire:model="reason" />
                    <div class="mt-3 grid gap-2">
                        <x-button wire:click="resolve(true)" wire:confirm="Teruskan dana ke teknisi dan tutup pesanan?">Teruskan dana ke teknisi</x-button>
                        <x-button variant="danger" wire:click="resolve(false)" wire:confirm="Batalkan pesanan dan kembalikan dana ke pelanggan?">Kembalikan dana ke pelanggan</x-button>
                    </div>
                </x-card>
            @elseif ($status->isActive())
                <x-card class="p-5">
                    <p class="font-bold">Batalkan pesanan</p>
                    <p class="mt-1 text-sm text-ink-soft">
                        {{ $order->fund_status?->value === 'ditahan' ? 'Dana yang sudah dibayar ditandai untuk dikembalikan ke pelanggan (transfer manual).' : 'Belum ada dana yang dibayar.' }}
                    </p>
                    <x-form.textarea class="mt-3" label="Alasan" name="reason" rows="3" wire:model="reason" />
                    <x-button variant="danger" class="mt-3 w-full" wire:click="cancel" wire:confirm="Batalkan pesanan ini?">Batalkan pesanan</x-button>
                </x-card>
            @endif
        </aside>
    </div>
</div>
