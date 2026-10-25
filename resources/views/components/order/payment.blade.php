@props([
    'order',
    'payment' => null,
    'lastAttempt' => null,
    'selectedMethod' => null,
])

@use('App\Enums\PaymentMethod')
@use('App\Enums\PaymentStatus')

@php
    /** @var \App\Models\Order $order */
    /** @var \App\Models\Payment|null $payment */
    /** @var \App\Models\Payment|null $lastAttempt */
    $selected = PaymentMethod::tryFrom((string) $selectedMethod);
    $failedAttempt = $payment === null && $lastAttempt !== null && in_array($lastAttempt->status, [PaymentStatus::Failed, PaymentStatus::Expired], true) ? $lastAttempt : null;
@endphp

@if ($payment)
    {{-- Waiting for the customer to pay --}}
    <div class="space-y-4" wire:poll.5s="refreshOrder">
        <div class="rounded-3xl bg-white p-5 ring-1 ring-line sm:p-7">
            <button type="button" wire:click="changePaymentMethod" wire:confirm="Batalkan instruksi ini dan pilih metode lain?" class="mb-5 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-ink">
                <flux:icon.arrow-left class="size-4" /> Ganti metode
            </button>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-mute">{{ $payment->method->label() }}</p>
                    <p class="mt-1 text-3xl font-extrabold tracking-tight tnum">{{ rupiah($payment->amount) }}</p>
                </div>
                <div class="rounded-2xl bg-sun-soft px-4 py-2.5 text-right"
                    x-data="{ left: {{ $payment->secondsLeft() }}, done: false }"
                    x-init="setInterval(() => { if (left > 0) { left-- } else if (! done) { done = true; $wire.refreshOrder() } }, 1000)">
                    <p class="text-xs text-sun-ink">Bayar sebelum</p>
                    <p class="text-xl font-extrabold tnum" x-text="String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0')">--:--</p>
                </div>
            </div>

            <div class="mt-6 rounded-2xl bg-paper p-5 sm:p-6">
                @if ($payment->method->usesQr() && $payment->qr_url)
                    <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-start">
                        <img src="{{ $payment->qr_url }}" alt="Kode QRIS untuk membayar {{ rupiah($payment->amount) }}" class="size-48 shrink-0 rounded-xl bg-white p-2 ring-1 ring-line" />
                        <div class="w-full">
                            <ol class="list-decimal space-y-1.5 pl-5 text-sm text-ink-soft">
                                <li>Buka {{ in_array($payment->method, [PaymentMethod::Qris], true) ? 'm-banking atau e-wallet yang mendukung QRIS' : 'aplikasi '.$payment->method->label() }}.</li>
                                <li>Scan kode QR ini.</li>
                                <li>Pastikan nama merchant dan nominal sesuai.</li>
                                <li>Halaman ini otomatis berpindah setelah pembayaran diterima.</li>
                            </ol>
                            @if ($payment->deeplink_url)
                                <x-button size="sm" variant="secondary" icon="smartphone" class="mt-4 sm:hidden" :href="$payment->deeplink_url">Buka aplikasi {{ $payment->method->label() }}</x-button>
                            @endif
                        </div>
                    </div>
                @elseif ($payment->method->isVirtualAccount() && $payment->va_number)
                    <div x-data="{ copied: null, copy(text, key) { navigator.clipboard?.writeText(text); this.copied = key; setTimeout(() => this.copied = null, 1500) } }">
                        @if ($payment->biller_code)
                            <p class="text-xs text-mute">Kode perusahaan</p>
                            <div class="mt-1 mb-4 flex flex-wrap items-center gap-3">
                                <p class="font-mono text-xl font-bold tracking-wider">{{ $payment->biller_code }}</p>
                                <button type="button" @click="copy('{{ $payment->biller_code }}', 'biller')" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-semibold ring-1 ring-line hover:ring-ink/30">
                                    <span x-text="copied === 'biller' ? 'Tersalin' : 'Salin'">Salin</span>
                                </button>
                            </div>
                        @endif
                        <p class="text-xs text-mute">{{ $payment->biller_code ? 'Kode pembayaran' : 'Nomor Virtual Account' }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-3">
                            <p class="font-mono text-2xl font-bold tracking-wider break-all">{{ trim(chunk_split($payment->va_number, 4, ' ')) }}</p>
                            <button type="button" @click="copy('{{ $payment->va_number }}', 'va')" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-semibold ring-1 ring-line hover:ring-ink/30">
                                <flux:icon.check class="size-3.5 text-leaf" x-show="copied === 'va'" x-cloak />
                                <flux:icon.copy class="size-3.5" x-show="copied !== 'va'" />
                                <span x-text="copied === 'va' ? 'Tersalin' : 'Salin'">Salin</span>
                            </button>
                        </div>
                        <ol class="mt-4 list-decimal space-y-1.5 pl-5 text-sm text-ink-soft">
                            @if ($payment->biller_code)
                                <li>Buka Livin' by Mandiri atau ATM Mandiri, pilih Bayar lalu Multipayment.</li>
                                <li>Masukkan kode perusahaan, lalu kode pembayaran di atas.</li>
                            @else
                                <li>Buka m-banking, pilih Transfer lalu Virtual Account.</li>
                                <li>Masukkan nomor di atas. Nominal akan muncul otomatis.</li>
                            @endif
                            <li>Pembayaran dicek otomatis, tidak perlu kirim bukti transfer.</li>
                        </ol>
                    </div>
                @else
                    <div class="flex items-start gap-4">
                        <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white ring-1 ring-line">
                            <flux:icon.smartphone class="size-5 text-ice" />
                        </span>
                        <div class="text-sm text-ink-soft">
                            <p class="font-bold text-ink">Buka aplikasi {{ $payment->method->label() }}</p>
                            <p class="mt-1">Lanjutkan pembayaran di aplikasi {{ $payment->method->label() }} lalu konfirmasi dengan PIN. Halaman ini otomatis berpindah setelah pembayaran diterima.</p>
                            @if ($payment->deeplink_url)
                                <x-button size="sm" class="mt-4" icon="smartphone" :href="$payment->deeplink_url">Bayar di {{ $payment->method->label() }}</x-button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-1.5 text-xs text-mute">
                    <flux:icon.shield-check class="size-3.5" /> Dana ditahan Sejuk sampai kamu mengonfirmasi pekerjaan selesai.
                </p>
                <button type="button" wire:click="checkPayment" wire:loading.attr="disabled" class="text-sm font-semibold text-ice hover:text-ice-deep">
                    <span wire:loading.remove wire:target="checkPayment">Saya sudah bayar</span>
                    <span wire:loading wire:target="checkPayment">Mengecek…</span>
                </button>
            </div>
        </div>
    </div>
@else
    {{-- Choose a method --}}
    <div class="rounded-3xl bg-white p-5 ring-1 ring-line sm:p-7">
        <div class="flex gap-4">
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-paper text-ice ring-1 ring-line">
                <flux:icon.credit-card class="size-5" />
            </span>
            <div>
                <h2 class="text-xl font-extrabold tracking-tight sm:text-2xl">Bayar biaya final</h2>
                <p class="mt-1 text-ink-soft">Pembayaran lewat Sejuk, tanpa tunai. Pekerjaan dimulai setelah pembayaran diterima.</p>
            </div>
        </div>

        @if ($failedAttempt)
            <div class="rise mt-6 flex items-start gap-3 rounded-2xl bg-coral-soft px-5 py-4 text-sm" role="alert">
                <flux:icon.circle-alert class="mt-0.5 size-4 shrink-0 text-coral" />
                <div>
                    <p class="font-bold text-coral">{{ $failedAttempt->status === PaymentStatus::Expired ? 'Batas waktu pembayaran habis' : 'Pembayaran gagal' }}</p>
                    <p class="mt-0.5 text-ink-soft">Tidak ada dana yang terpotong. Coba lagi atau pilih metode lain. Teknisi menunggu di lokasi.</p>
                </div>
            </div>
        @endif

        <div class="mt-6 flex items-start gap-3 rounded-2xl bg-frost px-5 py-4 text-sm">
            <flux:icon.shield-check class="mt-0.5 size-5 shrink-0 text-ice" />
            <p><span class="font-bold">Dana ditahan Sejuk</span> dan baru diteruskan ke teknisi setelah kamu menekan &ldquo;Selesai&rdquo;. Kalau AC masih bermasalah, dana tetap ditahan sampai diperbaiki.</p>
        </div>

        <div class="mt-7 space-y-6">
            @foreach (PaymentMethod::grouped() as $group => $methods)
                <fieldset wire:key="group-{{ $loop->index }}">
                    <legend class="mb-2.5 flex items-center gap-2 text-xs font-bold tracking-wider text-mute uppercase">
                        <flux:icon :icon="$methods[0]->groupIcon()" class="size-3.5" /> {{ $group }}
                    </legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($methods as $method)
                            @php $active = $selected === $method; @endphp
                            <button type="button" wire:key="method-{{ $method->value }}" wire:click="$set('paymentMethod', '{{ $method->value }}')" aria-pressed="{{ $active ? 'true' : 'false' }}" @class([
                                'flex items-center justify-between gap-3 rounded-2xl px-4 py-3.5 text-left transition',
                                'bg-ink text-white' => $active,
                                'bg-paper ring-1 ring-line hover:ring-ink/30' => ! $active,
                            ])>
                                <span>
                                    <span class="block font-semibold">{{ $method->label() }}</span>
                                    <span @class(['text-xs', 'text-white/60' => $active, 'text-mute' => ! $active])>{{ $method->hint() }}</span>
                                </span>
                                <span @class(['grid size-5 shrink-0 place-items-center rounded-full', 'bg-frost text-ink' => $active, 'ring-1 ring-line' => ! $active])>
                                    @if ($active) <flux:icon.check class="size-3.5" stroke-width="3" /> @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-line pt-6">
            <div>
                <p class="text-xs text-mute">Total dibayar</p>
                <p class="text-3xl font-extrabold tracking-tight tnum">{{ rupiah($order->final_total) }}</p>
            </div>
            <x-button size="lg" icon="lock" wire:click="startPayment" :disabled="$selected === null" wire:loading.attr="disabled" wire:target="startPayment">
                <span wire:loading.remove wire:target="startPayment">{{ $selected ? 'Bayar dengan '.$selected->label() : 'Pilih metode pembayaran' }}</span>
                <span wire:loading wire:target="startPayment">Menyiapkan pembayaran…</span>
            </x-button>
        </div>
    </div>
@endif
