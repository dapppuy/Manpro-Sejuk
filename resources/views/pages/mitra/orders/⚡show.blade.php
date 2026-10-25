<?php

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Services\OrderWorkflow;
use App\Services\TechnicianSearch;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::mitra')] class extends Component {
    public Order $order;

    public function mount(Order $order): void
    {
        Gate::authorize('view', $order);

        $this->order = $order;
    }

    #[Computed]
    public function isMine(): bool
    {
        return $this->order->technician_id === auth()->user()->technicianProfile->id;
    }

    #[Computed]
    public function distanceKm(): ?float
    {
        $profile = auth()->user()->technicianProfile;

        if ($this->order->latitude === null || $profile->latitude === null) {
            return null;
        }

        return TechnicianSearch::distanceKm((float) $profile->latitude, (float) $profile->longitude, $this->order->latitude, (float) $this->order->longitude);
    }

    #[Computed]
    public function customerCompletedOrders(): int
    {
        return $this->order->customer->orders()->where('status', OrderStatus::Selesai)->count();
    }

    public function reject(OrderWorkflow $workflow): void
    {
        Gate::authorize('respond', $this->order);

        try {
            $workflow->releaseToBackup($this->order, OfferStatus::Ditolak, auth()->user(), 'Ditolak teknisi');
        } catch (InvalidOrderTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Pesanan ditolak dan dikembalikan ke pelanggan untuk memilih teknisi cadangan.');
        $this->redirectRoute('mitra.orders.index', navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $this->order->refresh();
        app(OrderWorkflow::class)->expireIfOverdue($this->order);
        $this->order->load(['service', 'customer', 'media', 'costItems', 'workProofs']);

        return $this->view()->title('Pesanan #'.$this->order->code);
    }
}; ?>

@php
    $order = $this->order;
    $status = $order->status;
    $incoming = $status === OrderStatus::MenungguRespons && $this->isMine;
    $date = $order->scheduled_date ?? $order->preferred_date;
    $window = $order->scheduled_window ?? $order->time_window;
    $activeStatuses = [
        OrderStatus::Terjadwal, OrderStatus::MenungguPersetujuanBiaya, OrderStatus::MenungguPembayaran, OrderStatus::Dikerjakan,
        OrderStatus::PerbaikanUlang, OrderStatus::MenungguKonfirmasi, OrderStatus::Sengketa, OrderStatus::Selesai,
    ];
@endphp

<div @if ($incoming || in_array($status, [OrderStatus::MenungguPersetujuanBiaya, OrderStatus::MenungguPembayaran, OrderStatus::MenungguKonfirmasi], true)) wire:poll.15s @endif>
    <a href="{{ route('mitra.orders.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke pesanan
    </a>

    <div class="mt-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-mute tnum">Pesanan #{{ $order->code }}</p>
            <h1 class="mt-1 text-3xl font-extrabold">{{ $incoming ? 'Detail Pesanan Masuk' : $order->service->name }}</h1>
        </div>
        @if ($incoming)
            <span class="rounded-full bg-sun-soft px-3 py-1.5 text-sm font-bold text-sun-ink tnum"
                x-data="{ left: {{ $order->secondsToRespond() }} }" x-init="setInterval(() => left = Math.max(0, left - 1), 1000)"
                x-text="'Respons dalam ' + String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0')">Respons</span>
        @elseif (! $this->isMine)
            <x-pill>Tidak lagi ditujukan kepadamu</x-pill>
        @else
            <x-order.status-pill :status="$status" />
        @endif
    </div>

    <div class="mt-7 grid gap-6 lg:grid-cols-[1fr_300px]">
        <div class="space-y-5">
            @if ($this->isMine && $status === OrderStatus::EstimasiMasuk)
                <div class="flex items-start gap-3 rounded-2xl bg-frost p-5 text-sm ring-1 ring-ice/20">
                    <flux:icon.hourglass class="mt-0.5 size-5 shrink-0 text-ice" />
                    <div>
                        <p class="font-bold">Penawaran terkirim, menunggu pelanggan</p>
                        <p class="mt-1 text-ink-soft">Estimasi {{ rupiah($order->estimate_min) }} – {{ rupiah($order->estimate_max) }} untuk {{ $date->translatedFormat('l, j F') }}, {{ $window->range() }} WIB.</p>
                    </div>
                </div>
            @elseif ($this->isMine && in_array($status, $activeStatuses, true))
                @php
                    $finalSent = $order->final_total !== null && $status !== OrderStatus::Terjadwal;
                    $timeline = [
                        ['Penawaran diterima', 'Estimasi '.rupiah($order->estimate_min).'–'.rupiah($order->estimate_max), true],
                        ['Kunjungan teknisi', $date->translatedFormat('l, j F').', '.$window->range().' WIB', $finalSent],
                        ['Biaya final', match ($status) {
                            OrderStatus::Terjadwal => 'Isi setelah pemeriksaan',
                            OrderStatus::MenungguPersetujuanBiaya => rupiah($order->final_total).' · menunggu persetujuan pelanggan',
                            OrderStatus::MenungguPembayaran => rupiah($order->final_total).' · disetujui pelanggan',
                            default => rupiah($order->final_total).' · dibayar, dana ditahan Sejuk',
                        }, ! in_array($status, [OrderStatus::Terjadwal, OrderStatus::MenungguPersetujuanBiaya], true)],
                        ['Bukti kerja', match ($status) {
                            OrderStatus::Dikerjakan => 'Pembayaran diterima, kerjakan lalu unggah bukti',
                            OrderStatus::PerbaikanUlang => 'Pelanggan meminta perbaikan ulang ke-'.$order->rework_count.', tanpa biaya tambahan',
                            OrderStatus::MenungguKonfirmasi => 'Terkirim, menunggu konfirmasi pelanggan · otomatis selesai '.$order->autoCompletesAt()?->translatedFormat('j M, H.i'),
                            OrderStatus::Sengketa => 'Pelanggan masih belum puas setelah '.config('sejuk.max_rework').' kali perbaikan ulang. Tim Sejuk meninjau pesanan ini.',
                            OrderStatus::Selesai => 'Dikonfirmasi '.$order->completed_at?->translatedFormat('j M Y, H.i'),
                            OrderStatus::MenungguPembayaran => 'Menunggu pelanggan membayar',
                            default => 'Menunggu biaya final disetujui',
                        }, in_array($status, [OrderStatus::MenungguKonfirmasi, OrderStatus::Selesai], true)],
                    ];
                @endphp
                <x-card class="p-5 sm:p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-xl bg-frost text-ice"><flux:icon.calendar-clock class="size-5" /></span>
                        <div>
                            <p class="font-bold">{{ $status === OrderStatus::Terjadwal ? 'Kunjungan terjadwal' : $status->label() }}</p>
                            <p class="text-sm text-ink-soft">{{ $date->translatedFormat('l, j F') }}, {{ $window->range() }} WIB</p>
                        </div>
                    </div>
                    <ol class="mt-7 space-y-5 border-l-2 border-line pl-6">
                        @foreach ($timeline as $index => [$title, $detail, $done])
                            <li wire:key="t-{{ $index }}" class="relative">
                                <span @class(['absolute top-1 -left-[31px] size-3 rounded-full ring-4 ring-white', 'bg-ice' => $done, 'bg-line' => ! $done])></span>
                                <p class="font-semibold">{{ $title }}</p>
                                <p class="mt-0.5 text-sm text-mute">{{ $detail }}</p>
                            </li>
                        @endforeach
                    </ol>
                </x-card>

                @if ($status === OrderStatus::Selesai)
                    <div class="flex items-start gap-3 rounded-2xl bg-leaf-soft p-5 text-sm text-leaf">
                        <flux:icon.banknote class="mt-0.5 size-5 shrink-0" />
                        <div>
                            <p class="font-bold">Dana diteruskan: {{ rupiah($order->technicianPayout()) }}</p>
                            <p class="mt-1">Total {{ rupiah($order->final_total) }} dikurangi komisi Sejuk {{ config('sejuk.commission_percent') }}% ({{ rupiah($order->commission_amount) }}).</p>
                        </div>
                    </div>
                @endif

                @if ($status === OrderStatus::MenungguPersetujuanBiaya && $order->costItems->isNotEmpty())
                    <x-card class="overflow-hidden">
                        <ul>
                            @foreach ($order->costItems as $item)
                                <li wire:key="ci-{{ $item->id }}" @class(['flex items-center justify-between gap-4 border-b border-line px-5 py-3 text-sm', 'bg-sun-soft/50' => $item->is_additional])>
                                    <span>{{ $item->label }} @if ($item->is_additional)<span class="ml-1 rounded bg-sun px-1.5 py-0.5 text-[10px] font-bold uppercase">Baru</span>@endif</span>
                                    <span class="font-semibold tnum">{{ rupiah($item->amount) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="flex items-center justify-between bg-paper px-5 py-3 text-sm">
                            <span class="text-mute">Total</span>
                            <span class="text-lg font-extrabold tnum">{{ rupiah($order->final_total) }}</span>
                        </div>
                    </x-card>
                @endif
            @elseif ($this->isMine && $status === OrderStatus::Dibatalkan)
                <div class="rounded-2xl bg-ink/5 p-5 text-sm text-ink-soft">
                    {{ $order->cancelled_reason === \App\Services\OrderWorkflow::REASON_FINAL_COST_REJECTED ? 'Pelanggan menolak biaya final. Pekerjaan tidak dimulai dan pesanan ditutup.' : 'Pesanan dibatalkan oleh pelanggan.' }}
                </div>
            @endif

            <x-card class="p-5 sm:p-6">
                <h2 class="text-lg font-bold">Masalah AC pelanggan</h2>
                <p class="mt-3 leading-relaxed text-ink-soft">{{ $order->notes ?: 'Pelanggan tidak menambahkan catatan.' }}</p>
                <dl class="mt-5 grid gap-4 border-t border-line pt-5 sm:grid-cols-2">
                    @foreach ([
                        'Jenis layanan' => $order->service->name,
                        'Unit AC' => $order->brandLabel().' · '.$order->ac_capacity->label(),
                        'Jadwal' => $date->translatedFormat('l, j M').', '.$window->range().' WIB',
                        'Jarak' => $this->distanceKm !== null ? number_format($this->distanceKm, 1, ',', '.').' km' : '–',
                    ] as $label => $value)
                        <div wire:key="d-{{ $loop->index }}">
                            <dt class="text-xs text-mute">{{ $label }}</dt>
                            <dd class="mt-1 font-semibold">{{ $value }}</dd>
                        </div>
                    @endforeach
                    @if ($order->warranty)
                        <div>
                            <dt class="text-xs text-mute">Garansi</dt>
                            <dd class="mt-1 font-semibold">{{ config('sejuk.warranty.days') }} hari (+{{ rupiah($order->warranty_fee) }})</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            <x-card class="p-5 sm:p-6">
                <h2 class="text-lg font-bold">Lokasi pelanggan</h2>
                <p class="mt-3 flex items-start gap-2 text-ink-soft">
                    <flux:icon.map-pin class="mt-0.5 size-4 shrink-0 text-ice" />
                    {{ $order->fullAddress() }}
                </p>
                @if ($order->latitude && $order->longitude && $this->isMine && in_array($status, [OrderStatus::Terjadwal], true))
                    <a href="https://www.google.com/maps/search/?api=1&query={{ $order->latitude }},{{ $order->longitude }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-ice">Buka di peta <flux:icon.external-link class="size-3.5" /></a>
                @endif
            </x-card>

            @if ($order->media->isNotEmpty())
                <x-card class="p-5 sm:p-6">
                    <h2 class="text-lg font-bold">Foto/video dari pelanggan</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($order->media as $media)
                            @if ($media->type->value === 'video')
                                <video wire:key="m-{{ $media->id }}" src="{{ $media->url() }}" controls preload="metadata" class="aspect-square w-full rounded-xl bg-ink object-cover"></video>
                            @else
                                <a wire:key="m-{{ $media->id }}" href="{{ $media->url() }}" target="_blank">
                                    <img src="{{ $media->url() }}" alt="Foto kerusakan" class="aspect-square w-full rounded-xl object-cover" />
                                </a>
                            @endif
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>

        <x-card class="h-fit p-5">
            <p class="text-xs font-bold tracking-wider text-mute uppercase">Pelanggan</p>
            <div class="mt-4 flex items-center gap-3">
                <x-avatar :name="$order->customer->name" class="!size-12 rounded-full" />
                <div>
                    <p class="font-bold">{{ $order->customer->name }}</p>
                    <p class="text-xs text-mute">{{ $this->customerCompletedOrders }} pesanan selesai</p>
                </div>
            </div>
            @if ($incoming)
                <div class="mt-6 space-y-2">
                    <x-button class="w-full" icon="send" :href="route('mitra.orders.estimate', $order)" wire:navigate>Kirim Penawaran</x-button>
                    <x-button class="w-full" variant="danger" wire:click="reject" wire:confirm="Tolak pesanan ini? Pelanggan akan memilih teknisi cadangan.">Tolak pesanan</x-button>
                </div>
            @elseif ($this->isMine && in_array($status, $activeStatuses, true))
                <div class="mt-5 space-y-2">
                    @if (in_array($status, [OrderStatus::Dikerjakan, OrderStatus::PerbaikanUlang], true))
                        <x-button size="lg" class="w-full" icon="camera" :href="route('mitra.orders.proof', $order)" wire:navigate>Upload Bukti Kerja</x-button>
                    @endif
                    @if ($status === OrderStatus::Terjadwal)
                        <x-button size="lg" class="w-full" icon="receipt-text" :href="route('mitra.orders.final-cost', $order)" wire:navigate>Input Biaya Final</x-button>
                    @elseif ($status === OrderStatus::MenungguPersetujuanBiaya)
                        <x-button variant="secondary" class="w-full" icon="pencil" :href="route('mitra.orders.final-cost', $order)" wire:navigate>Ubah biaya final</x-button>
                    @endif
                    @if ($order->customer->phone)
                        <x-button variant="secondary" size="sm" icon="message-circle" class="w-full" :href="'https://wa.me/62'.substr($order->customer->phone, 1)" target="_blank" rel="noopener">Hubungi pelanggan</x-button>
                    @endif
                </div>
            @endif
        </x-card>
    </div>
</div>
