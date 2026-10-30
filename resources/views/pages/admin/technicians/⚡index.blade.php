<?php

use App\Enums\TechnicianBadge;
use App\Enums\VerificationStatus;
use App\Models\TechnicianProfile;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Teknisi')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, TechnicianProfile>
     */
    #[Computed]
    public function technicians(): LengthAwarePaginator
    {
        return TechnicianProfile::query()
            ->with('user')
            ->where('verification_status', VerificationStatus::Approved)
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('business_name', 'like', "%{$this->search}%")
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$this->search}%"))))
            ->orderBy('business_name')
            ->paginate(20);
    }

    public function changeBadge(int $technicianId, string $badge): void
    {
        $technician = TechnicianProfile::findOrFail($technicianId);
        $technician->update(['badge' => TechnicianBadge::tryFrom($badge) ?? abort(422)]);

        Flux::toast(text: "Badge {$technician->business_name} diubah.");
    }

    public function toggleActive(int $technicianId): void
    {
        $technician = TechnicianProfile::findOrFail($technicianId);
        $technician->update(['is_active' => ! $technician->is_active]);

        Flux::toast(text: $technician->business_name.($technician->is_active ? ' diaktifkan.' : ' dinonaktifkan.'));
    }

    /**
     * Pin a technician on top of search results with the PROMOSI label, optionally for a number of days.
     */
    public function setPromotion(int $technicianId, int $days): void
    {
        abort_unless(in_array($days, [0, -1, 7, 30], true), 422);
        $technician = TechnicianProfile::findOrFail($technicianId);

        $technician->update(match ($days) {
            0 => ['is_promoted' => false, 'promoted_until' => null],
            -1 => ['is_promoted' => true, 'promoted_until' => null],
            default => ['is_promoted' => true, 'promoted_until' => now()->addDays($days)->endOfDay()],
        });

        Flux::toast(text: $days === 0 ? "Promosi {$technician->business_name} dihentikan." : "{$technician->business_name} dipromosikan.");
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Teknisi</h1>
            <p class="mt-1 text-ink-soft">Teknisi terverifikasi. Teknisi nonaktif tidak muncul di pencarian.</p>
        </div>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau usaha" class="h-11 w-full rounded-full bg-white px-4 text-sm ring-1 ring-line outline-none focus:ring-ice sm:w-64" />
    </div>

    <x-card class="mt-6 overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="bg-paper text-xs tracking-wider text-mute uppercase">
                <tr>
                    <th class="px-5 py-3 font-semibold">Teknisi</th>
                    <th class="px-5 py-3 font-semibold">Badge</th>
                    <th class="px-5 py-3 font-semibold">Rating</th>
                    <th class="px-5 py-3 font-semibold">Promosi</th>
                    <th class="px-5 py-3 text-right font-semibold">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->technicians as $technician)
                    <tr wire:key="tech-{{ $technician->id }}" class="border-t border-line">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('technicians.show', $technician) }}" target="_blank" class="font-semibold hover:text-ice">{{ $technician->business_name }}</a>
                            <p class="text-xs text-mute">{{ $technician->user->name }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <select wire:change="changeBadge({{ $technician->id }}, $event.target.value)" class="h-9 rounded-full bg-white px-3 text-xs font-semibold ring-1 ring-line outline-none" aria-label="Badge {{ $technician->business_name }}">
                                @foreach (TechnicianBadge::cases() as $case)
                                    <option value="{{ $case->value }}" @selected($technician->badge === $case)>{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-5 py-3.5"><x-rating :value="$technician->rating_avg" :count="$technician->reviews_count" /></td>
                        <td class="px-5 py-3.5">
                            <select wire:change="setPromotion({{ $technician->id }}, $event.target.value)" class="h-9 rounded-full bg-white px-3 text-xs font-semibold ring-1 ring-line outline-none" aria-label="Promosi {{ $technician->business_name }}">
                                <option value="0" @selected(! $technician->isPromotedNow())>Tidak</option>
                                <option value="7">7 hari</option>
                                <option value="30">30 hari</option>
                                <option value="-1">Tanpa batas</option>
                            </select>
                            @if ($technician->isPromotedNow())
                                <p class="mt-1 text-[11px] text-sun-ink">{{ $technician->promoted_until ? 'Sampai '.$technician->promoted_until->translatedFormat('j M') : 'Aktif tanpa batas' }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <button type="button" wire:click="toggleActive({{ $technician->id }})" @class(['rounded-full px-3 py-1 text-xs font-bold', 'bg-leaf-soft text-leaf' => $technician->is_active, 'bg-coral-soft text-coral' => ! $technician->is_active])>
                                {{ $technician->is_active ? 'Aktif' : 'Nonaktif' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-mute">Tidak ada teknisi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $this->technicians->links() }}</div>
</div>
