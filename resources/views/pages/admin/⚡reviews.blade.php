<?php

use App\Actions\Reviews\ToggleReviewVisibility;
use App\Models\Review;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Ulasan')] class extends Component {
    use WithPagination;

    #[Url]
    public string $filter = 'semua';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Review>
     */
    #[Computed]
    public function reviews(): LengthAwarePaginator
    {
        return Review::query()
            ->with(['customer', 'technician', 'order'])
            ->when($this->filter === 'disembunyikan', fn ($query) => $query->where('is_hidden', true))
            ->when($this->filter === 'rendah', fn ($query) => $query->where('rating', '<=', 2))
            ->latest()
            ->paginate(20);
    }

    public function toggle(int $reviewId, ToggleReviewVisibility $toggle): void
    {
        $review = $toggle->handle(Review::findOrFail($reviewId));

        Flux::toast(text: $review->is_hidden ? 'Ulasan disembunyikan dari profil teknisi.' : 'Ulasan ditampilkan lagi.');
    }
}; ?>

<div>
    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Ulasan</h1>
    <p class="mt-1 text-ink-soft">Sembunyikan ulasan yang melanggar aturan (kata kasar, data pribadi, promosi). Rating teknisi ikut disesuaikan.</p>

    <div class="mt-6 flex gap-2">
        @foreach (['semua' => 'Semua', 'rendah' => 'Rating ≤ 2', 'disembunyikan' => 'Disembunyikan'] as $value => $label)
            <x-chip wire:key="f-{{ $value }}" :active="$filter === $value" wire:click="$set('filter', '{{ $value }}')">{{ $label }}</x-chip>
        @endforeach
    </div>

    <div class="mt-6 space-y-3">
        @forelse ($this->reviews as $review)
            <x-card wire:key="r-{{ $review->id }}" @class(['p-5', 'opacity-60' => $review->is_hidden])>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-bold">{{ $review->customer->name }} → {{ $review->technician->business_name }}</p>
                        <p class="text-xs text-mute">
                            {{ $review->created_at?->translatedFormat('j M Y') }}
                            @if ($review->order) · <a href="{{ route('admin.orders.show', $review->order) }}" wire:navigate class="hover:text-ice">#{{ $review->order->code }}</a> @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <x-rating :value="$review->rating" />
                        <x-button size="sm" :variant="$review->is_hidden ? 'secondary' : 'danger'" wire:click="toggle({{ $review->id }})">{{ $review->is_hidden ? 'Tampilkan' : 'Sembunyikan' }}</x-button>
                    </div>
                </div>
                @if ($review->comment)
                    <p class="mt-3 text-sm text-ink-soft">{{ $review->comment }}</p>
                @endif
            </x-card>
        @empty
            <x-card class="px-5 py-10 text-center text-sm text-mute">Tidak ada ulasan.</x-card>
        @endforelse
    </div>

    <div class="mt-4">{{ $this->reviews->links() }}</div>
</div>
