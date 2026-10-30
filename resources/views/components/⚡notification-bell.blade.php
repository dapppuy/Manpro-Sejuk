<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $dark = false;

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function latest(): Collection
    {
        return auth()->user()->notifications()->latest()->limit(8)->get();
    }

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');

        if (str_starts_with($url, url('/'))) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        unset($this->unreadCount, $this->latest);
    }
}; ?>

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" wire:poll.30s>
    <button type="button" @click="open = ! open" :aria-expanded="open" aria-label="Notifikasi{{ $this->unreadCount ? ', '.$this->unreadCount.' belum dibaca' : '' }}"
        @class(['relative grid size-9 place-items-center rounded-full transition', 'text-white/70 hover:bg-white/10 hover:text-white' => $dark, 'bg-white text-ink ring-1 ring-line hover:ring-ink/30' => ! $dark])>
        <flux:icon.bell class="size-[18px]" />
        @if ($this->unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 grid min-w-4.5 place-items-center rounded-full bg-coral px-1 text-[10px] font-bold text-white tnum">{{ min($this->unreadCount, 9) }}{{ $this->unreadCount > 9 ? '+' : '' }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.origin.top
        @class(['absolute z-50 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl bg-white text-ink shadow-2xl shadow-ink/15 ring-1 ring-line', 'left-0' => $dark, 'right-0' => ! $dark])>
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <p class="text-sm font-bold">Notifikasi</p>
            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="text-xs font-semibold text-ice hover:text-ice-deep">Tandai semua dibaca</button>
            @endif
        </div>
        <div class="max-h-96 overflow-y-auto">
            @forelse ($this->latest as $notification)
                <button type="button" wire:key="n-{{ $notification->id }}" wire:click="open('{{ $notification->id }}')"
                    @class(['flex w-full gap-3 border-b border-line px-4 py-3 text-left last:border-b-0 hover:bg-paper', 'bg-frost/40' => $notification->read_at === null])>
                    <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-coral' => $notification->read_at === null, 'bg-transparent' => $notification->read_at !== null])></span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold">{{ $notification->data['title'] ?? 'Pembaruan' }}</span>
                        <span class="mt-0.5 line-clamp-2 block text-xs text-ink-soft">{{ $notification->data['body'] ?? '' }}</span>
                        <span class="mt-1 block text-[11px] text-mute">{{ $notification->created_at?->diffForHumans() }}</span>
                    </span>
                </button>
            @empty
                <p class="px-4 py-8 text-center text-sm text-mute">Belum ada notifikasi.</p>
            @endforelse
        </div>
    </div>
</div>
