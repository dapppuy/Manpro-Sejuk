<?php

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Log audit')] class extends Component {
    use WithPagination;

    #[Url]
    public string $action = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('actor')
            ->when(array_key_exists($this->action, AuditLog::ACTIONS), fn ($query) => $query->where('action', $this->action))
            ->when($this->search !== '', fn ($query) => $query->where('description', 'like', "%{$this->search}%"))
            ->latest('id')
            ->paginate(30);
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Log audit</h1>
            <p class="mt-1 text-ink-soft">Tindakan penting admin. Riwayat status setiap pesanan ada di halaman detail pesanan.</p>
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <select wire:model.live="action" class="h-11 rounded-full bg-white px-4 text-sm ring-1 ring-line outline-none" aria-label="Jenis tindakan">
                <option value="">Semua tindakan</option>
                @foreach (AuditLog::ACTIONS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari keterangan" class="h-11 rounded-full bg-white px-4 text-sm ring-1 ring-line outline-none focus:ring-ice sm:w-60" />
        </div>
    </div>

    <x-card class="mt-6 overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="bg-paper text-xs tracking-wider text-mute uppercase">
                <tr>
                    <th class="px-5 py-3 font-semibold">Waktu</th>
                    <th class="px-5 py-3 font-semibold">Admin</th>
                    <th class="px-5 py-3 font-semibold">Tindakan</th>
                    <th class="px-5 py-3 font-semibold">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->logs as $log)
                    <tr wire:key="log-{{ $log->id }}" class="border-t border-line align-top">
                        <td class="px-5 py-3 whitespace-nowrap text-mute tnum">{{ $log->created_at?->translatedFormat('j M Y, H.i') }}</td>
                        <td class="px-5 py-3 font-semibold">{{ $log->actor->name ?? 'Sistem' }}</td>
                        <td class="px-5 py-3"><x-pill>{{ $log->actionLabel() }}</x-pill></td>
                        <td class="px-5 py-3 break-words text-ink-soft">{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-mute">Belum ada catatan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $this->logs->links() }}</div>
</div>
