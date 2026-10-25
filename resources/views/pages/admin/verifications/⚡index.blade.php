<?php

use App\Enums\VerificationStatus;
use App\Models\TechnicianProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Verifikasi teknisi')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'pending';

    public function updatedStatus(): void
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
            ->with(['user', 'services', 'documents', 'experience'])
            ->where('verification_status', VerificationStatus::tryFrom($this->status) ?? VerificationStatus::Pending)
            ->oldest()
            ->paginate(15);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return TechnicianProfile::query()
            ->selectRaw('verification_status, count(*) as total')
            ->groupBy('verification_status')
            ->pluck('total', 'verification_status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}; ?>

<div>
    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Verifikasi teknisi</h1>
    <p class="mt-1 text-ink-soft">Periksa KTP dan bukti kompetensi, lalu tentukan badge.</p>

    <div class="no-scrollbar mt-6 flex gap-2 overflow-x-auto">
        @foreach (VerificationStatus::cases() as $case)
            <x-chip wire:key="tab-{{ $case->value }}" :active="$status === $case->value" wire:click="$set('status', '{{ $case->value }}')">
                {{ $case->label() }} <span class="ml-1 tnum opacity-70">{{ $this->counts[$case->value] ?? 0 }}</span>
            </x-chip>
        @endforeach
    </div>

    <x-card class="mt-6 overflow-hidden">
        @forelse ($this->technicians as $technician)
            <a wire:key="tech-{{ $technician->id }}" href="{{ route('admin.verifications.show', $technician) }}" wire:navigate class="flex flex-wrap items-center gap-4 border-b border-line px-5 py-4 last:border-b-0 hover:bg-paper">
                <x-avatar :name="$technician->user->name" class="rounded-2xl" />
                <div class="min-w-0 flex-1">
                    <p class="font-bold">{{ $technician->business_name }}</p>
                    <p class="text-sm text-ink-soft">{{ $technician->user->name }} · {{ $technician->base_area }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <x-pill>{{ $technician->documents->contains('type', \App\Enums\DocumentType::Sertifikat) ? 'Sertifikat' : 'Pengalaman '.($technician->experience->years ?? 0).' th' }}</x-pill>
                    <span class="text-mute">{{ $technician->created_at?->diffForHumans() }}</span>
                </div>
                <flux:icon.chevron-right class="size-4 text-mute" />
            </a>
        @empty
            <div class="px-6 py-14 text-center">
                <flux:icon.circle-check class="mx-auto size-8 text-leaf" />
                <p class="mt-3 font-bold">Tidak ada teknisi di daftar ini</p>
            </div>
        @endforelse
    </x-card>

    <div class="mt-4">{{ $this->technicians->links() }}</div>
</div>
