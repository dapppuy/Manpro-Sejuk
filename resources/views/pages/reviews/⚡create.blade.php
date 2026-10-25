<?php

use App\Actions\Reviews\SubmitReview;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::customer'), Title('Beri ulasan')] class extends Component {
    public Order $order;

    public int $rating = 0;

    public string $comment = '';

    public bool $sent = false;

    public function mount(Order $order): void
    {
        Gate::authorize('manage', $order);

        if ($order->status !== OrderStatus::Selesai) {
            $this->redirectRoute('orders.show', $order);

            return;
        }

        $this->order = $order->load(['technician.user', 'service', 'review']);
        $this->sent = $order->review !== null;
    }

    public function setRating(int $rating): void
    {
        $this->rating = max(1, min(5, $rating));
    }

    public function submit(SubmitReview $submitReview): void
    {
        Gate::authorize('manage', $this->order);

        $validated = $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], ['rating.between' => 'Pilih rating 1 sampai 5 bintang.'], ['comment' => 'cerita pengalaman']);

        try {
            $submitReview->handle($this->order, (int) $validated['rating'], trim($validated['comment'] ?? '') ?: null);
        } catch (InvalidOrderTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }

        $this->order->load('review');
        $this->sent = $this->order->review !== null;
    }
}; ?>

@php
    $technician = $order->technician;
    $firstName = str($technician?->user->name)->before(' ');
    $review = $order->review;
@endphp

<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <a href="{{ route('orders.show', $order) }}" wire:navigate class="mb-6 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke detail pesanan
    </a>
    <p class="text-sm text-mute tnum">Pesanan #{{ $order->code }}</p>
    <h1 class="mt-1 text-3xl font-extrabold tracking-tight">{{ $order->service->name }}</h1>
    <x-order.progress :status="$order->status" class="mt-8" />

    <div class="mt-8">
        @if ($sent && $review)
            <x-order.panel class="rise">
                <div class="flex flex-col items-center py-6 text-center sm:py-8">
                    <span class="grid size-16 place-items-center rounded-full bg-leaf-soft text-leaf">
                        <flux:icon.circle-check class="size-8" />
                    </span>
                    <h2 class="mt-5 text-2xl font-extrabold">Terima kasih atas ulasanmu</h2>
                    <p class="mt-2 max-w-md text-ink-soft">Penilaianmu membantu pelanggan lain memilih teknisi yang tepat.</p>
                    <div class="mt-5 flex gap-1" aria-label="Rating {{ $review->rating }} dari 5">
                        @foreach (range(1, 5) as $star)
                            <flux:icon.star wire:key="given-{{ $star }}" @class(['size-6', 'fill-sun text-sun' => $star <= $review->rating, 'text-line' => $star > $review->rating]) />
                        @endforeach
                    </div>
                    @if ($review->comment)
                        <p class="mt-4 max-w-md rounded-2xl bg-paper px-4 py-3 text-sm text-ink-soft">“{{ $review->comment }}”</p>
                    @endif
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <x-button :href="route('orders.show', $order)" wire:navigate>Kembali ke detail pesanan</x-button>
                        @if ($technician)
                            <x-button variant="secondary" :href="route('technicians.show', $technician).'#ulasan'" wire:navigate>Lihat di profil teknisi</x-button>
                        @endif
                    </div>
                </div>
            </x-order.panel>
        @else
            <x-order.panel icon="star" :title="'Bagaimana pelayanan '.$firstName.'?'" sub="Berikan penilaian berdasarkan ketepatan waktu, komunikasi, dan hasil pengerjaan.">
                <form wire:submit="submit">
                    <div class="mt-7">
                        <p class="text-sm font-bold">Rating layanan<span class="ml-0.5 text-coral">*</span></p>
                        <div class="mt-3 flex gap-1 sm:gap-2" role="group" aria-label="Pilih rating" x-data="{ hover: 0 }" @mouseleave="hover = 0">
                            @foreach (range(1, 5) as $value)
                                <button type="button" wire:key="star-{{ $value }}" wire:click="setRating({{ $value }})" @mouseenter="hover = {{ $value }}"
                                    aria-label="{{ $value }} bintang" aria-pressed="{{ $rating === $value ? 'true' : 'false' }}" class="rounded-xl p-1.5 hover:bg-sun-soft sm:p-2">
                                    <flux:icon.star class="size-8 transition-colors"
                                        x-bind:class="(hover ? hover >= {{ $value }} : {{ $rating }} >= {{ $value }}) ? 'fill-sun text-sun' : 'text-line'" />
                                </button>
                            @endforeach
                        </div>
                        @if ($rating > 0)
                            <p class="mt-2 text-sm font-semibold text-sun-ink">{{ ['', 'Sangat kecewa', 'Kurang puas', 'Cukup', 'Puas', 'Sangat puas'][$rating] }}</p>
                        @endif
                        @error('rating') <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-6">
                        <label for="review-comment" class="text-sm font-bold">Ceritakan pengalamanmu</label>
                        <textarea id="review-comment" wire:model="comment" rows="5" maxlength="1000"
                            placeholder="Contoh: Teknisi datang tepat waktu dan menjelaskan kerusakan dengan jelas."
                            class="mt-2 w-full rounded-2xl bg-paper px-4 py-3 text-sm ring-1 ring-inset ring-line outline-none placeholder:text-mute focus:ring-ice"></textarea>
                        @error('comment') <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
                    </div>

                    <x-button type="submit" size="lg" class="mt-7" :disabled="$rating === 0" wire:loading.attr="disabled">Kirim ulasan</x-button>
                    @if ($rating === 0)
                        <p class="mt-2 text-xs text-mute">Pilih rating untuk melanjutkan.</p>
                    @endif
                </form>
            </x-order.panel>
        @endif
    </div>
</div>
