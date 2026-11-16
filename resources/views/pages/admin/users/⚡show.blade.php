<?php

use App\Actions\Admin\ModerateAccount;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component {
    public User $user;

    public string $warningReason = '';

    public string $suspensionReason = '';

    public function mount(User $user): void
    {
        abort_if($user->isAdmin(), 404);

        $this->user = $user;
    }

    public function warn(ModerateAccount $moderate): void
    {
        $this->validate(['warningReason' => ['required', 'string', 'min:10', 'max:500']], attributes: ['warningReason' => 'isi peringatan']);

        $moderate->warn($this->user, $this->warningReason, auth()->user());
        $this->reset('warningReason');

        Flux::toast(variant: 'success', text: 'Peringatan dikirim ke '.$this->user->name.'.');
    }

    public function suspend(ModerateAccount $moderate): void
    {
        $this->validate(['suspensionReason' => ['required', 'string', 'min:10', 'max:500']], attributes: ['suspensionReason' => 'alasan penangguhan']);

        $moderate->suspend($this->user, $this->suspensionReason, auth()->user());
        $this->reset('suspensionReason');

        Flux::toast(variant: 'warning', text: $this->user->name.' ditangguhkan.');
    }

    public function restore(ModerateAccount $moderate): void
    {
        $moderate->restore($this->user, auth()->user());

        Flux::toast(variant: 'success', text: $this->user->name.' dipulihkan.');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $this->user->refresh()->load(['technicianProfile', 'warnings' => fn ($query) => $query->with('admin')->latest()]);

        return $this->view()->title($this->user->name);
    }
}; ?>

@php
    $user = $this->user;
    $orders = $user->isTechnician()
        ? \App\Models\Order::where('technician_id', $user->technicianProfile?->id)->latest()->limit(8)->get()
        : $user->orders()->latest()->limit(8)->get();
@endphp

<div>
    <a href="{{ route('admin.users.index', ['role' => $user->role->value]) }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke daftar akun
    </a>

    <div class="mt-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-mute">{{ $user->role->label() }} · bergabung {{ $user->created_at?->translatedFormat('j F Y') }}</p>
            <h1 class="mt-1 text-2xl font-extrabold sm:text-3xl">{{ $user->name }}</h1>
        </div>
        <x-pill :tone="$user->isSuspended() ? 'coral' : 'leaf'">{{ $user->isSuspended() ? 'Ditangguhkan' : 'Aktif' }}</x-pill>
    </div>

    <div class="mt-7 grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            <x-card class="p-5 sm:p-6">
                <x-section-title icon="user-round" title="Data akun" description="Dokumen identitas teknisi hanya dibuka dari menu verifikasi." />
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    @foreach (array_filter([
                        'Email' => $user->email.($user->email_verified_at ? ' · terverifikasi' : ' · belum diverifikasi'),
                        'WhatsApp' => $user->phone,
                        'Kota' => $user->city,
                        'Usaha' => $user->technicianProfile?->business_name,
                    ]) as $label => $value)
                        <div wire:key="d-{{ $loop->index }}">
                            <dt class="text-xs text-mute">{{ $label }}</dt>
                            <dd class="mt-1 font-semibold break-words">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($user->isSuspended())
                    <p class="mt-5 rounded-xl bg-coral-soft p-3 text-sm text-coral">Ditangguhkan {{ $user->suspended_at->translatedFormat('j M Y, H.i') }}: {{ $user->suspension_reason }}</p>
                @endif
            </x-card>

            <x-card class="p-5 sm:p-6">
                <x-section-title icon="clipboard-list" title="Pesanan terakhir" />
                <ul class="mt-4 divide-y divide-line text-sm">
                    @forelse ($orders as $order)
                        <li wire:key="o-{{ $order->id }}" class="flex items-center justify-between gap-3 py-2.5">
                            <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="font-semibold hover:text-ice">#{{ $order->code }}</a>
                            <span class="text-mute">{{ $order->created_at?->translatedFormat('j M Y') }}</span>
                            <x-order.status-pill :status="$order->status" />
                        </li>
                    @empty
                        <li class="py-3 text-mute">Belum ada pesanan.</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card class="p-5 sm:p-6">
                <x-section-title icon="triangle-alert" title="Riwayat peringatan" />
                <ul class="mt-4 space-y-3 text-sm">
                    @forelse ($user->warnings as $warning)
                        <li wire:key="w-{{ $warning->id }}" class="rounded-xl bg-paper p-3">
                            <p>{{ $warning->reason }}</p>
                            <p class="mt-1 text-xs text-mute">{{ $warning->created_at?->translatedFormat('j M Y, H.i') }} · {{ $warning->admin->name ?? 'Admin' }}</p>
                        </li>
                    @empty
                        <li class="text-mute">Belum pernah diberi peringatan.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-10 lg:self-start">
            <x-card class="p-5">
                <p class="font-bold">Beri peringatan</p>
                <p class="mt-1 text-xs text-mute">Dikirim ke email dan notifikasi akun.</p>
                <x-form.textarea class="mt-3" label="Isi peringatan" name="warningReason" rows="3" wire:model="warningReason" placeholder="Misal: menerima pembayaran tunai di luar aplikasi." />
                <x-button variant="secondary" class="mt-3 w-full" icon="triangle-alert" wire:click="warn">Kirim peringatan</x-button>
            </x-card>

            <x-card class="p-5">
                @if ($user->isSuspended())
                    <p class="font-bold">Pulihkan akun</p>
                    <p class="mt-1 text-xs text-mute">Akun bisa masuk lagi{{ $user->isTechnician() ? ' dan profil teknisi diaktifkan kembali' : '' }}.</p>
                    <x-button class="mt-3 w-full" icon="circle-check" wire:click="restore" wire:confirm="Pulihkan akun ini?">Pulihkan</x-button>
                @else
                    <p class="font-bold">Tangguhkan akun</p>
                    <p class="mt-1 text-xs text-mute">Akun langsung keluar dan tidak bisa masuk{{ $user->isTechnician() ? '; profil teknisi disembunyikan dari pencarian' : '' }}.</p>
                    <x-form.textarea class="mt-3" label="Alasan" name="suspensionReason" rows="3" wire:model="suspensionReason" placeholder="Misal: tiga peringatan dalam sebulan." />
                    <x-button variant="danger" class="mt-3 w-full" wire:click="suspend" wire:confirm="Tangguhkan akun ini?">Tangguhkan</x-button>
                @endif
            </x-card>
        </aside>
    </div>
</div>
