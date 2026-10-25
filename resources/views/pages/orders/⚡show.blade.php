<?php

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\TechnicianProfile;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Services\Midtrans\MidtransException;
use App\Services\OrderWorkflow;
use App\Services\PaymentService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::customer')] class extends Component {
    public Order $order;

    public bool $confirmRejectFinal = false;

    public ?string $paymentMethod = null;

    public bool $showReworkForm = false;

    public string $complaint = '';

    public function mount(Order $order): void
    {
        Gate::authorize('manage', $order);

        $this->order = $order;
        $this->expireIfOverdue();
    }

    /**
     * Pick up changes made by the technician or the scheduler while the page is open.
     */
    public function refreshOrder(): void
    {
        $this->order->refresh();
        $this->expireIfOverdue();
        $this->syncPendingPayment(onlyIfStale: true);
        unset($this->backupCandidates, $this->pendingPayment, $this->lastPaymentAttempt);
    }

    public function startPayment(PaymentService $payments): void
    {
        $method = PaymentMethod::tryFrom((string) $this->paymentMethod);

        if ($method === null) {
            Flux::toast(variant: 'danger', text: 'Pilih metode pembayaran dulu.');

            return;
        }

        try {
            $payments->start($this->order, $method);
        } catch (InvalidOrderTransition|MidtransException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }

        $this->refreshOrder();
    }

    public function changePaymentMethod(PaymentService $payments): void
    {
        if ($this->pendingPayment !== null) {
            $payments->cancel($this->pendingPayment);
        }

        $this->refreshOrder();
    }

    /**
     * "Saya sudah bayar": check with Midtrans right away instead of waiting for the webhook.
     */
    public function checkPayment(): void
    {
        $this->syncPendingPayment(onlyIfStale: false);
        $this->order->refresh();
        unset($this->pendingPayment, $this->lastPaymentAttempt);

        if ($this->order->status === OrderStatus::MenungguPembayaran && $this->pendingPayment !== null) {
            Flux::toast(text: 'Pembayaran belum kami terima. Halaman ini akan berpindah otomatis setelah diterima.');
        }
    }

    #[Computed]
    public function pendingPayment(): ?Payment
    {
        return app(PaymentService::class)->pendingPayment($this->order);
    }

    #[Computed]
    public function lastPaymentAttempt(): ?Payment
    {
        return $this->order->payments()->latest('id')->first();
    }

    public function requestBackup(OrderWorkflow $workflow): void
    {
        $this->runAction(fn () => $workflow->releaseToBackup($this->order, OfferStatus::Kedaluwarsa, auth()->user(), 'Pelanggan memilih teknisi cadangan'));
    }

    public function chooseBackup(int $technicianId, OrderWorkflow $workflow): void
    {
        $technician = $this->backupCandidates->firstWhere('id', $technicianId);

        if (! $technician instanceof TechnicianProfile) {
            Flux::toast(variant: 'danger', text: 'Teknisi ini sudah tidak tersedia. Pilih yang lain.');
            unset($this->backupCandidates);

            return;
        }

        $this->runAction(fn () => $workflow->reassign($this->order, $technician, auth()->user()));
    }

    public function approveEstimate(OrderWorkflow $workflow): void
    {
        $this->runAction(fn () => $workflow->approveEstimate($this->order, auth()->user()));
    }

    public function rejectEstimate(OrderWorkflow $workflow): void
    {
        $businessName = $this->order->technician?->business_name;

        $this->runAction(fn () => $workflow->rejectEstimate($this->order, auth()->user()));

        if ($this->order->status === OrderStatus::CariCadangan) {
            session([
                'sejuk.hidden_technicians' => $this->order->offers()->pluck('technician_id')->all(),
                'sejuk.pending_order' => $this->order->code,
            ]);
            session()->flash('sejuk.rejected_notice', "Estimasi dari {$businessName} ditolak. Pilih teknisi lain, formulirmu tetap tersimpan.");

            $this->redirectRoute('search', ['layanan' => $this->order->service->slug], navigate: true);
        }
    }

    public function approveFinalCost(OrderWorkflow $workflow): void
    {
        $this->runAction(fn () => $workflow->approveFinalCost($this->order, auth()->user()));
    }

    public function rejectFinalCost(OrderWorkflow $workflow): void
    {
        $this->runAction(fn () => $workflow->rejectFinalCost($this->order, auth()->user()));
        $this->confirmRejectFinal = false;
    }

    public function confirmCompletion(OrderWorkflow $workflow): void
    {
        $this->runAction(fn () => $workflow->confirmCompletion($this->order, auth()->user()));
    }

    public function requestRework(OrderWorkflow $workflow): void
    {
        $this->validate(['complaint' => ['nullable', 'string', 'max:500']], attributes: ['complaint' => 'keluhan']);

        $this->runAction(fn () => $workflow->requestRework($this->order, auth()->user(), trim($this->complaint) ?: null));
        $this->reset('showReworkForm', 'complaint');
    }

    public function findOtherTechnician(): void
    {
        session([
            'sejuk.hidden_technicians' => $this->order->offers()->pluck('technician_id')->all(),
            'sejuk.pending_order' => $this->order->code,
        ]);

        $this->redirectRoute('search', ['layanan' => $this->order->service->slug], navigate: true);
    }

    public function cancel(OrderWorkflow $workflow): void
    {
        $this->runAction(fn () => $workflow->cancelByCustomer($this->order, auth()->user()));
    }

    /**
     * @return Collection<int, TechnicianProfile>
     */
    #[Computed]
    public function backupCandidates(): Collection
    {
        if ($this->order->status !== OrderStatus::CariCadangan) {
            return new Collection;
        }

        return app(OrderWorkflow::class)->backupCandidates($this->order);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $this->order->loadMissing(['technician.user', 'service', 'media', 'offers', 'costItems', 'payments', 'workProofs']);

        return $this->view()->title('Pesanan #'.$this->order->code);
    }

    private function runAction(Closure $action): void
    {
        try {
            $action();
        } catch (InvalidOrderTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }

        $this->refreshOrder();
    }

    private function syncPendingPayment(bool $onlyIfStale): void
    {
        $payment = app(PaymentService::class)->pendingPayment($this->order);

        if ($payment === null) {
            return;
        }

        if ($onlyIfStale && $payment->synced_at !== null && $payment->synced_at->gt(now()->subSeconds(15)) && ! $payment->isOverdue()) {
            return;
        }

        app(PaymentService::class)->sync($payment);
        $this->order->refresh();
    }

    private function expireIfOverdue(): void
    {
        if (app(OrderWorkflow::class)->expireIfOverdue($this->order)) {
            $this->order->refresh();
        }
    }
}; ?>

@php
    $order = $this->order;
    $technician = $order->technician;
    $status = $order->status;
    $firstName = str($technician?->user->name)->before(' ');
    $scheduleDate = $order->scheduled_date ?? $order->preferred_date;
    $scheduleWindow = $order->scheduled_window ?? $order->time_window;
    $declined = $order->offers->firstWhere(fn ($offer) => $offer->technician_id === $order->technician_id && $offer->status === OfferStatus::Ditolak);
    $expired = $order->offers->firstWhere(fn ($offer) => $offer->technician_id === $order->technician_id && $offer->status === OfferStatus::Kedaluwarsa);
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6"
    @if (in_array($status, [OrderStatus::MenungguRespons, OrderStatus::EstimasiMasuk, OrderStatus::Terjadwal, OrderStatus::MenungguPersetujuanBiaya, OrderStatus::Dikerjakan, OrderStatus::PerbaikanUlang], true)) wire:poll.10s="refreshOrder" @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-mute tnum">Pesanan #{{ $order->code }}</p>
            <h1 class="mt-1 text-3xl font-extrabold tracking-tight">{{ $order->service->name }}</h1>
        </div>
        <x-order.status-pill :status="$status" />
    </div>

    @if ($status !== OrderStatus::Dibatalkan)
        <x-order.progress :status="$status" class="mt-8" />
    @endif

    <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_340px]">
        <div wire:key="stage-{{ $status->value }}" class="rise min-w-0 space-y-4">
            @switch($status)
                @case(OrderStatus::MenungguRespons)
                    <x-order.panel icon="hourglass" :title="'Menunggu respons '.$firstName" sub="Kami memberi teknisi waktu maksimal {{ config('sejuk.response_minutes') }} menit untuk menanggapi permintaanmu.">
                        <div class="mt-6 grid gap-3 sm:grid-cols-3"
                            x-data="{ left: {{ $order->secondsToRespond() }}, done: false, tick() { if (this.left > 0) { this.left--; } else if (! this.done) { this.done = true; $wire.refreshOrder() } } }"
                            x-init="setInterval(() => tick(), 1000)">
                            <div class="rounded-2xl bg-paper p-4">
                                <p class="font-bold tnum">{{ ($order->pendingOffer()?->created_at ?? $order->created_at)?->format('H.i') }}</p>
                                <p class="mt-1 text-xs text-mute">Permintaan terkirim</p>
                            </div>
                            <div class="rounded-2xl bg-paper p-4">
                                <p class="font-bold tnum" x-text="String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0')">--:--</p>
                                <p class="mt-1 text-xs text-mute">Sisa waktu respons</p>
                            </div>
                            <div class="rounded-2xl bg-paper p-4">
                                <p class="font-bold">Otomatis</p>
                                <p class="mt-1 text-xs text-mute">Teknisi cadangan</p>
                            </div>
                        </div>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <x-button variant="secondary" icon="search" wire:click="requestBackup" wire:confirm="Berhenti menunggu {{ $firstName }} dan pilih teknisi cadangan?">
                                Cari teknisi cadangan sekarang
                            </x-button>
                        </div>
                    </x-order.panel>
                    <div class="rounded-2xl bg-frost p-5 text-sm ring-1 ring-ice/20">
                        <p class="font-bold text-ice-deep">Respons Cepat & Teknisi Cadangan</p>
                        <p class="mt-1 text-ink-soft">Jika teknisi awal tidak merespons, detail pesananmu tetap tersimpan dan bisa langsung dikirim ke teknisi lain tanpa mengisi formulir ulang.</p>
                    </div>
                    @break

                @case(OrderStatus::CariCadangan)
                    <x-order.panel icon="search" title="Teknisi cadangan siap membantu" sub="Pilih teknisi lain yang tersedia. Detail permintaan dan jadwalmu tidak berubah.">
                        @if ($declined || $expired)
                            <p class="mt-5 rounded-2xl bg-sun-soft px-4 py-3 text-sm text-sun-ink">
                                {{ $technician?->business_name }} {{ $declined ? 'tidak bisa mengambil pesanan ini.' : 'belum merespons dalam '.config('sejuk.response_minutes').' menit.' }}
                            </p>
                        @endif
                        <div class="mt-6 space-y-3">
                            @forelse ($this->backupCandidates as $candidate)
                                <div wire:key="backup-{{ $candidate->id }}" class="flex flex-wrap items-center gap-4 rounded-2xl bg-paper p-4 ring-1 ring-line">
                                    <x-avatar :name="$candidate->user->name" :src="$candidate->user->avatarUrl()" class="!size-14 rounded-xl" />
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold">{{ $candidate->business_name }}</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <x-rating :value="$candidate->rating_avg" />
                                            <span class="text-xs text-mute tnum">{{ number_format((float) $candidate->distanceKm, 1, ',', '.') }} km</span>
                                            <span @class(['text-xs font-semibold', 'text-leaf' => $candidate->isAvailableToday(), 'text-mute' => ! $candidate->isAvailableToday()])>
                                                {{ $candidate->isAvailableToday() ? 'Tersedia hari ini' : 'Jadwal berikutnya' }}
                                            </span>
                                        </div>
                                    </div>
                                    <x-button size="sm" wire:click="chooseBackup({{ $candidate->id }})" wire:loading.attr="disabled">Pilih teknisi</x-button>
                                </div>
                            @empty
                                <p class="rounded-2xl bg-paper px-4 py-5 text-sm text-mute">Belum ada teknisi cadangan di sekitarmu untuk layanan ini.</p>
                            @endforelse
                        </div>
                        <button type="button" wire:click="findOtherTechnician" class="mt-5 text-sm font-semibold text-ice hover:text-ice-deep">Cari dari semua teknisi →</button>
                    </x-order.panel>
                    @break

                @case(OrderStatus::EstimasiMasuk)
                    <x-order.panel icon="circle-check" title="Estimasi sudah masuk" sub="Berdasarkan formulirmu dan daftar harga acuan teknisi.">
                        <div class="mt-6 rounded-2xl bg-ink p-5 text-white sm:p-6">
                            <p class="text-sm text-white/60">Kisaran biaya</p>
                            <p class="mt-1 text-3xl font-extrabold tracking-tight tnum sm:text-4xl">
                                {{ rupiah($order->estimate_min) }} <span class="text-white/40">–</span> {{ rupiah($order->estimate_max) }}
                            </p>
                            <p class="mt-3 text-sm text-white/60">Biaya final dipastikan setelah pemeriksaan di lokasi. Kalau berubah, pekerjaan menunggu persetujuanmu.</p>
                        </div>
                        <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-paper p-4">
                                <dt class="flex items-center gap-1.5 text-xs text-mute"><flux:icon.clock class="size-3.5" /> Jadwal kedatangan</dt>
                                <dd class="mt-1 font-bold">{{ $scheduleDate->translatedFormat('l, j F') }}, {{ $scheduleWindow->range() }} WIB</dd>
                            </div>
                            @if ($order->estimate_note)
                                <div class="rounded-2xl bg-paper p-4">
                                    <dt class="text-xs text-mute">Catatan teknisi</dt>
                                    <dd class="mt-1 text-sm text-ink-soft">{{ $order->estimate_note }}</dd>
                                </div>
                            @endif
                        </dl>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <x-button size="lg" wire:click="approveEstimate" wire:loading.attr="disabled">Setujui estimasi</x-button>
                            <x-button size="lg" variant="secondary" wire:click="rejectEstimate" wire:confirm="Tolak estimasi ini dan cari teknisi lain? Formulirmu tetap tersimpan.">Tolak, cari teknisi lain</x-button>
                        </div>
                    </x-order.panel>
                    @break

                @case(OrderStatus::Terjadwal)
                    <x-order.panel tone="frost" icon="calendar-check" title="Pesanan terjadwal" :sub="$technician?->user->name.' akan datang sesuai jadwal di bawah.'">
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl bg-white p-5 ring-1 ring-ice/15">
                                <p class="flex items-center gap-1.5 text-xs text-mute"><flux:icon.clock class="size-3.5" /> Kedatangan</p>
                                <p class="mt-1 text-lg font-bold">{{ $scheduleDate->translatedFormat('l, j F') }}</p>
                                <p class="text-2xl font-extrabold tnum sm:text-3xl">{{ $scheduleWindow->range() }} WIB</p>
                            </div>
                            <div class="rounded-2xl bg-white p-5 ring-1 ring-ice/15">
                                <p class="flex items-center gap-1.5 text-xs text-mute"><flux:icon.map-pin class="size-3.5" /> Lokasi</p>
                                <p class="mt-1 font-bold">{{ $order->address }}</p>
                                <p class="text-ink-soft">{{ $order->address_detail ?: 'Tanpa detail tambahan' }}</p>
                            </div>
                        </div>
                        <p class="mt-5 flex items-start gap-2 text-sm text-ink-soft">
                            <flux:icon.bell-ring class="mt-0.5 size-4 shrink-0 text-ice" />
                            Pengingat dikirim sehari sebelum dan 1 jam sebelum teknisi datang. Status diperbarui di halaman ini.
                        </p>
                    </x-order.panel>
                    <x-order.panel>
                        <p class="font-bold">Langkah berikutnya</p>
                        <p class="mt-1 text-sm text-ink-soft">Setelah memeriksa AC di lokasi, teknisi mengirim rincian biaya final. Pekerjaan baru dimulai setelah kamu menyetujuinya.</p>
                    </x-order.panel>
                    @break

                @case(OrderStatus::MenungguPersetujuanBiaya)
                    @php
                        $changed = $order->costItems->contains('is_additional', true);
                        $over = $order->amountOverEstimate();
                    @endphp
                    <x-order.panel icon="circle-alert" icon-class="text-sun-ink"
                        :title="$changed ? 'Biaya berubah setelah pemeriksaan' : 'Konfirmasi biaya final'"
                        :sub="$changed ? 'Teknisi menemukan pekerjaan tambahan. Pekerjaan belum dimulai sampai kamu menyetujui.' : 'Biaya final masih dalam kisaran estimasi. Setujui untuk memulai pekerjaan.'">
                        <div class="mt-6 overflow-hidden rounded-2xl ring-1 ring-line">
                            <ul>
                                @foreach ($order->costItems as $item)
                                    <li wire:key="cost-{{ $item->id }}" @class(['flex items-center justify-between gap-4 border-b border-line px-4 py-3.5 sm:px-5', 'bg-sun-soft/50' => $item->is_additional])>
                                        <span class="flex flex-wrap items-center gap-2">
                                            {{ $item->label }}
                                            @if ($item->is_additional)
                                                <span class="rounded bg-sun px-1.5 py-0.5 text-[10px] font-bold text-ink uppercase">Baru</span>
                                            @endif
                                        </span>
                                        <span class="shrink-0 font-semibold tnum">{{ rupiah($item->amount) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="grid grid-cols-2 bg-paper">
                                <div class="border-r border-line px-4 py-4 sm:px-5">
                                    <p class="text-xs text-mute">Estimasi awal</p>
                                    <p class="mt-0.5 font-semibold text-ink-soft tnum">{{ rupiah($order->estimate_min) }} – {{ rupiah($order->estimate_max) }}</p>
                                </div>
                                <div class="px-4 py-4 sm:px-5">
                                    <p class="text-xs text-mute">Total biaya final</p>
                                    <p class="mt-0.5 text-2xl font-extrabold tnum">{{ rupiah($order->final_total) }}</p>
                                    @if ($over > 0)
                                        <p class="text-xs font-semibold text-coral tnum">+{{ rupiah($over) }} di atas estimasi</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @if ($order->warranty)
                            <p class="mt-3 text-xs text-mute">Total termasuk Garansi Servis {{ config('sejuk.warranty.days') }} Hari yang kamu pilih.</p>
                        @endif

                        @if (! $confirmRejectFinal)
                            <div class="mt-8 flex flex-wrap gap-3">
                                <x-button size="lg" icon="check" wire:click="approveFinalCost" wire:loading.attr="disabled">Setujui & lanjut bayar</x-button>
                                <x-button size="lg" variant="danger" wire:click="$set('confirmRejectFinal', true)">Tolak biaya final</x-button>
                            </div>
                        @else
                            <div class="rise mt-8 rounded-2xl bg-coral-soft p-5">
                                <p class="font-bold">Tolak biaya final?</p>
                                <p class="mt-1 text-sm text-ink-soft">Pekerjaan tidak akan dimulai dan pesanan ditutup. Biaya kunjungan: <span class="font-semibold">{{ config('sejuk.visit_fee') > 0 ? rupiah(config('sejuk.visit_fee')) : 'gratis' }}</span>.</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <x-button variant="danger" wire:click="rejectFinalCost">Ya, tolak</x-button>
                                    <x-button variant="ghost" wire:click="$set('confirmRejectFinal', false)">Kembali</x-button>
                                </div>
                            </div>
                        @endif
                    </x-order.panel>
                    @break

                @case(OrderStatus::MenungguPembayaran)
                    <x-order.payment :order="$order" :payment="$this->pendingPayment" :last-attempt="$this->lastPaymentAttempt" :selected-method="$paymentMethod" />
                    @break

                @case(OrderStatus::Dikerjakan)
                    <x-order.panel tone="frost" icon="hammer" title="Sedang dikerjakan"
                        :sub="'Pembayaran diterima dan ditahan Sejuk. '.$firstName.' sedang mengerjakan AC-mu, dan kamu akan diminta mengecek hasilnya sebelum dana diteruskan.'" />
                    <x-order.panel>
                        <p class="font-bold">Setelah pekerjaan selesai</p>
                        <p class="mt-1 text-sm text-ink-soft">Teknisi mengunggah foto sebelum dan sesudah. Kamu memeriksa hasilnya di halaman ini sebelum dana diteruskan.</p>
                    </x-order.panel>
                    @break

                @case(OrderStatus::MenungguKonfirmasi)
                    @php $proof = $order->latestProof(); @endphp
                    <x-order.panel icon="circle-check" title="Cek dulu sebelum menandai selesai" sub="Teknisi sudah mengunggah bukti kerja. Pastikan AC benar-benar beres selagi teknisi masih di lokasi.">
                        @if ($order->rework_count > 0)
                            <p class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-frost px-3 py-1 text-xs font-semibold text-ice-deep">
                                <flux:icon.rotate-ccw class="size-3.5" /> Bukti baru setelah perbaikan ulang
                            </p>
                        @endif
                        @if ($proof)
                            <div class="mt-6 grid grid-cols-2 gap-3">
                                @foreach (['sebelum' => 'Sebelum', 'sesudah' => 'Sesudah'] as $side => $label)
                                    <figure wire:key="proof-{{ $proof->id }}-{{ $side }}" class="relative overflow-hidden rounded-2xl bg-paper">
                                        <a href="{{ $proof->photoUrl($side) }}" target="_blank">
                                            <img src="{{ $proof->photoUrl($side) }}" alt="Foto {{ strtolower($label) }} pengerjaan" class="aspect-[4/3] w-full object-cover" />
                                        </a>
                                        <figcaption class="absolute top-3 left-3 rounded-full bg-ink/75 px-3 py-1 text-xs font-bold text-white backdrop-blur">{{ $label }}</figcaption>
                                    </figure>
                                @endforeach
                            </div>
                            @if ($proof->note)
                                <p class="mt-4 rounded-2xl bg-paper px-4 py-3 text-sm text-ink-soft"><span class="font-semibold text-ink">Catatan teknisi:</span> {{ $proof->note }}</p>
                            @endif
                        @endif
                        <ul class="mt-6 grid gap-2 text-sm sm:grid-cols-3">
                            @foreach (['AC menyala dan dingin', 'Tidak ada tetesan air', 'Tidak berisik'] as $check)
                                <li wire:key="check-{{ $loop->index }}" class="flex items-center gap-2 rounded-xl bg-paper px-3 py-2.5">
                                    <flux:icon.check class="size-4 text-ice" /> {{ $check }}
                                </li>
                            @endforeach
                        </ul>

                        @if (! $showReworkForm)
                            <div class="mt-8 flex flex-wrap gap-3">
                                <x-button size="lg" wire:click="confirmCompletion" wire:confirm="Tandai selesai dan teruskan dana ke teknisi?">Selesai, AC sudah beres</x-button>
                                <x-button size="lg" variant="secondary" icon="undo-2" wire:click="$set('showReworkForm', true)">Masih bermasalah</x-button>
                            </div>
                        @else
                            <div class="rise mt-8 rounded-2xl bg-sun-soft p-5">
                                <p class="font-bold">Apa yang masih bermasalah?</p>
                                <p class="mt-1 text-sm text-ink-soft">Teknisi akan memperbaiki lagi tanpa biaya tambahan. Dana tetap ditahan Sejuk.</p>
                                <x-form.textarea class="mt-3" label="Keluhan (opsional)" name="complaint" rows="3" wire:model="complaint" placeholder="Contoh: masih ada tetesan air dari unit dalam." />
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <x-button wire:click="requestRework">Minta perbaikan ulang</x-button>
                                    <x-button variant="ghost" wire:click="$set('showReworkForm', false)">Kembali</x-button>
                                </div>
                            </div>
                        @endif
                        <p class="mt-4 text-xs text-mute">
                            Menekan &ldquo;Selesai&rdquo; meneruskan {{ rupiah($order->final_total) }} ke {{ $technician?->business_name }}.
                            Kalau tidak ada konfirmasi, pesanan ditutup dan dana diteruskan otomatis pada {{ $order->autoCompletesAt()?->translatedFormat('j F, H.i') }} WIB.
                        </p>
                    </x-order.panel>
                    @break

                @case(OrderStatus::PerbaikanUlang)
                    <x-order.panel icon="rotate-ccw" title="Menunggu perbaikan ulang"
                        :sub="'Kami sudah memberi tahu '.$firstName.' bahwa AC masih bermasalah. Teknisi akan memperbaiki lagi lalu mengunggah bukti baru, tanpa biaya tambahan. Dana tetap ditahan Sejuk.'" />
                    <x-order.panel>
                        <p class="font-bold">Perbaikan ulang ke-{{ $order->rework_count }} dari {{ config('sejuk.max_rework') }}</p>
                        <p class="mt-1 text-sm text-ink-soft">Halaman ini berpindah otomatis saat bukti baru diunggah. Kalau setelah {{ config('sejuk.max_rework') }} kali perbaikan ulang AC masih bermasalah, tim Sejuk akan meninjau pesananmu.</p>
                    </x-order.panel>
                    @break

                @case(OrderStatus::Sengketa)
                    <x-order.panel icon="shield-check" title="Ditinjau tim Sejuk" sub="AC masih bermasalah setelah beberapa kali perbaikan ulang. Tim Sejuk akan menghubungimu untuk mencari penyelesaian. Dana tetap ditahan selama peninjauan." />
                    @break

                @case(OrderStatus::Selesai)
                    @php $paid = $order->paidPayment(); @endphp
                    <x-order.panel>
                        <div class="flex flex-col items-center py-4 text-center">
                            <span class="grid size-16 place-items-center rounded-full bg-leaf-soft text-leaf">
                                <flux:icon.check class="size-8" stroke-width="3" />
                            </span>
                            <h2 class="mt-5 text-2xl font-extrabold tracking-tight sm:text-3xl">AC kamu sudah beres</h2>
                            <p class="mt-2 text-ink-soft">Pesanan ditutup. Terima kasih sudah memakai Sejuk.</p>
                        </div>
                        <div class="mt-6 rounded-2xl bg-paper p-5">
                            <dl class="space-y-2.5 text-sm">
                                <div class="flex justify-between gap-4"><dt class="text-mute">Layanan</dt><dd class="text-right font-medium">{{ $order->service->name }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-mute">Teknisi</dt><dd class="text-right font-medium">{{ $technician?->business_name }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-mute">Dikerjakan</dt><dd class="text-right font-medium">{{ $scheduleDate->translatedFormat('l, j F') }}, {{ $scheduleWindow->range() }} WIB</dd></div>
                            </dl>
                            <ul class="mt-4 space-y-2 border-t border-line pt-4 text-sm">
                                @foreach ($order->costItems as $item)
                                    <li wire:key="done-{{ $item->id }}" class="flex justify-between gap-4">
                                        <span class="text-ink-soft">{{ $item->label }}</span>
                                        <span class="font-medium tnum">{{ rupiah($item->amount) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="mt-4 flex items-center justify-between border-t border-line pt-4">
                                <span class="flex items-center gap-2 font-bold">Total <span class="rounded-full bg-leaf-soft px-2 py-0.5 text-[11px] font-bold text-leaf">Lunas</span></span>
                                <span class="text-2xl font-extrabold tnum">{{ rupiah($order->final_total) }}</span>
                            </div>
                            @if ($paid)
                                <dl class="mt-4 space-y-2.5 border-t border-line pt-4 text-sm">
                                    <div class="flex justify-between gap-4"><dt class="text-mute">Metode</dt><dd class="text-right font-medium">{{ $paid->method->label() }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-mute">Dibayar</dt><dd class="text-right font-medium tnum">{{ $paid->paid_at?->translatedFormat('j M, H.i') }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-mute">Status dana</dt><dd class="text-right font-medium">Diteruskan ke {{ $firstName }}</dd></div>
                                </dl>
                            @endif
                        </div>
                        <div class="mt-8 grid gap-3 sm:grid-cols-2">
                            @if (Route::has('reviews.create'))
                                <x-button size="lg" icon="star" :href="route('reviews.create', $order)" wire:navigate>Beri ulasan</x-button>
                            @endif
                            <x-button size="lg" variant="secondary" icon-trailing="arrow-right" :href="route('home')" wire:navigate>Ke beranda</x-button>
                        </div>
                    </x-order.panel>
                    @break

                @case(OrderStatus::Dibatalkan)
                    @php $finalRejected = $order->cancelled_reason === OrderWorkflow::REASON_FINAL_COST_REJECTED; @endphp
                    <x-order.panel icon="circle-x" icon-class="text-ink-soft"
                        :title="$finalRejected ? 'Pekerjaan tidak dimulai' : 'Pesanan dibatalkan'"
                        :sub="$finalRejected ? 'Kamu menolak biaya final. Teknisi tidak mengerjakan apa pun dan pesanan ditutup.' : 'Pesanan dibatalkan sebelum teknisi berangkat, tanpa biaya apa pun.'">
                        <div class="mt-5 flex items-center justify-between rounded-2xl bg-paper px-5 py-4 text-sm">
                            <span>{{ $finalRejected ? 'Biaya kunjungan' : 'Biaya pembatalan' }}</span>
                            <span class="font-bold tnum">{{ $finalRejected ? rupiah(config('sejuk.visit_fee')) : 'Rp0' }}</span>
                        </div>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <x-button size="lg" :href="route('search', ['layanan' => $order->service->slug])" wire:navigate>Cari teknisi lain</x-button>
                            <x-button size="lg" variant="secondary" :href="route('home')" wire:navigate>Ke beranda</x-button>
                        </div>
                    </x-order.panel>
                    @break

                @default
                    <x-order.panel icon="info" :title="$status->label()" sub="Tahap ini sedang disiapkan." />
            @endswitch
        </div>

        <aside class="h-fit space-y-4 lg:sticky lg:top-24">
            @if ($technician)
                <x-card class="p-5">
                    <p class="text-xs font-semibold tracking-wider text-mute uppercase">Teknisi</p>
                    <div class="mt-3 flex items-center gap-3">
                        <x-avatar :name="$technician->user->name" :src="$technician->user->avatarUrl()" class="!size-14 rounded-xl" />
                        <div class="min-w-0">
                            <a href="{{ route('technicians.show', $technician) }}" wire:navigate class="font-bold hover:text-ice">{{ $technician->business_name }}</a>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <x-badge-chip :badge="$technician->badge" />
                                <x-rating :value="$technician->rating_avg" />
                            </div>
                        </div>
                    </div>
                    @if ($status === OrderStatus::Terjadwal && $technician->user->phone)
                        <x-button variant="secondary" size="sm" icon="message-circle" class="mt-4 w-full" :href="'https://wa.me/62'.substr($technician->user->phone, 1)" target="_blank" rel="noopener">
                            Hubungi via WhatsApp
                        </x-button>
                    @endif
                </x-card>
            @endif

            <x-card class="p-5 text-sm">
                <p class="text-xs font-semibold tracking-wider text-mute uppercase">Detail permintaan</p>
                <dl class="mt-3 space-y-2.5">
                    @foreach (array_filter([
                        'AC' => $order->brandLabel().' · '.$order->ac_capacity->label(),
                        'Lokasi' => $order->fullAddress(),
                        'Jadwal' => $scheduleDate->translatedFormat('l, j F').', '.($order->scheduled_window ? $scheduleWindow->range().' WIB' : str($scheduleWindow->label())->lower()),
                        'Estimasi' => $order->estimate_min ? rupiah($order->estimate_min).' – '.rupiah($order->estimate_max) : null,
                        'Garansi' => $order->warranty ? config('sejuk.warranty.days').' hari' : null,
                        'Biaya final' => $order->final_total !== null && ! in_array($status, [OrderStatus::Terjadwal, OrderStatus::Dibatalkan], true) ? rupiah($order->final_total) : null,
                        'Pembayaran' => $order->paidPayment() ? $order->paidPayment()->method->label().' · '.($order->fund_status === \App\Enums\FundStatus::Ditahan ? 'Ditahan Sejuk' : 'Diteruskan') : null,
                    ]) as $label => $value)
                        <div wire:key="row-{{ $loop->index }}" class="flex justify-between gap-4">
                            <dt class="shrink-0 text-mute">{{ $label }}</dt>
                            <dd class="text-right font-medium tnum">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($order->notes)
                    <p class="mt-3 rounded-xl bg-paper px-3 py-2 text-xs text-ink-soft">“{{ $order->notes }}”</p>
                @endif
                @if ($order->media->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($order->media as $media)
                            <a wire:key="m-{{ $media->id }}" href="{{ $media->url() }}" target="_blank">
                                @if ($media->type->value === 'video')
                                    <span class="grid size-12 place-items-center rounded-lg bg-ink text-[10px] text-white">Video</span>
                                @else
                                    <img src="{{ $media->url() }}" alt="Foto kerusakan" class="size-12 rounded-lg object-cover" />
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-card>

            @if ($status->isCancellableByCustomer())
                <button type="button" wire:click="cancel" wire:confirm="Batalkan pesanan ini? Tidak ada biaya pembatalan." class="w-full rounded-full py-2.5 text-sm font-semibold text-coral hover:bg-coral-soft">
                    Batalkan pesanan · gratis
                </button>
            @endif
        </aside>
    </div>
</div>
