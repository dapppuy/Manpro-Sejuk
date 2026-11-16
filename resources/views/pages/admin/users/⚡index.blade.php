<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Kelola akun')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $role = 'customer';

    #[Url]
    public bool $suspendedOnly = false;

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('technicianProfile')
            ->withCount(['orders', 'warnings'])
            ->where('role', UserRole::tryFrom($this->role) === UserRole::Technician ? UserRole::Technician : UserRole::Customer)
            ->when($this->suspendedOnly, fn ($query) => $query->whereNotNull('suspended_at'))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->latest()
            ->paginate(20);
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Kelola akun</h1>
            <p class="mt-1 text-ink-soft">Pelanggan dan teknisi. Buka akun untuk memberi peringatan atau menangguhkan.</p>
        </div>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama, email, atau nomor" class="h-11 w-full rounded-full bg-white px-4 text-sm ring-1 ring-line outline-none focus:ring-ice sm:w-72" />
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-2">
        @foreach (['customer' => 'Pelanggan', 'technician' => 'Teknisi'] as $value => $label)
            <button type="button" wire:key="role-{{ $value }}" wire:click="$set('role', '{{ $value }}')" @class(['rounded-full px-4 py-2 text-sm font-semibold ring-1', 'bg-ink text-white ring-ink' => $role === $value, 'bg-white text-ink-soft ring-line hover:text-ink' => $role !== $value])>{{ $label }}</button>
        @endforeach
        <label class="ml-auto flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" wire:model.live="suspendedOnly" class="size-4 accent-ice" /> Hanya yang ditangguhkan
        </label>
    </div>

    <x-card class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="bg-paper text-xs tracking-wider text-mute uppercase">
                <tr>
                    <th class="px-5 py-3 font-semibold">Akun</th>
                    <th class="px-5 py-3 font-semibold">Kontak</th>
                    <th class="px-5 py-3 font-semibold">{{ $role === 'technician' ? 'Usaha' : 'Pesanan' }}</th>
                    <th class="px-5 py-3 font-semibold">Peringatan</th>
                    <th class="px-5 py-3 text-right font-semibold">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="border-t border-line">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="font-semibold hover:text-ice">{{ $user->name }}</a>
                            <p class="text-xs text-mute">Bergabung {{ $user->created_at?->translatedFormat('j M Y') }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <p>{{ $user->email }}</p>
                            <p class="text-xs text-mute">{{ $user->phone ?? '–' }}</p>
                        </td>
                        <td class="px-5 py-3.5">{{ $role === 'technician' ? ($user->technicianProfile->business_name ?? '–') : $user->orders_count }}</td>
                        <td class="px-5 py-3.5 tnum">{{ $user->warnings_count }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <x-pill :tone="$user->isSuspended() ? 'coral' : 'leaf'">{{ $user->isSuspended() ? 'Ditangguhkan' : 'Aktif' }}</x-pill>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-mute">Tidak ada akun.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $this->users->links() }}</div>
</div>
