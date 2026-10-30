<?php

use App\Models\Order;
use App\Models\Payout;
use App\Models\TechnicianProfile;
use App\Services\TechnicianEarnings;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::mitra'), Title('Pendapatan')] class extends Component {
    public bool $editingAccount = false;

    public string $bank_name = '';

    public string $account_name = '';

    public string $account_number = '';

    #[Computed]
    public function profile(): TechnicianProfile
    {
        return auth()->user()->technicianProfile->load('payoutAccount');
    }

    /**
     * @return array{held: int, forwarded: int, paid_out: int}
     */
    #[Computed]
    public function balances(): array
    {
        return app(TechnicianEarnings::class)->balances($this->profile);
    }

    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function ledger(): Collection
    {
        return app(TechnicianEarnings::class)->ledger($this->profile);
    }

    /**
     * @return Collection<int, Payout>
     */
    #[Computed]
    public function payouts(): Collection
    {
        return $this->profile->payouts()->latest('transferred_at')->limit(20)->get();
    }

    public function editAccount(): void
    {
        $account = $this->profile->payoutAccount;

        $this->bank_name = $account->bank_name ?? '';
        $this->account_name = $account->account_name ?? auth()->user()->name;
        $this->account_number = $account->account_number ?? '';
        $this->editingAccount = true;
    }

    public function saveAccount(): void
    {
        $this->account_number = preg_replace('/\D+/', '', $this->account_number) ?? '';

        $validated = $this->validate([
            'bank_name' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'digits_between:6,20'],
        ], attributes: ['bank_name' => 'nama bank', 'account_name' => 'nama pemilik rekening', 'account_number' => 'nomor rekening']);

        $this->profile->payoutAccount()->updateOrCreate([], $validated);

        unset($this->profile);
        $this->editingAccount = false;

        Flux::toast(variant: 'success', text: 'Rekening pencairan disimpan.');
    }
}; ?>

@php
    $balances = $this->balances;
    $account = $this->profile->payoutAccount;
@endphp

<div>
    <p class="text-sm text-mute">Sejuk Mitra</p>
    <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Pendapatan</h1>
    <p class="mt-2 max-w-2xl text-ink-soft">Dana pelanggan ditahan Sejuk sampai pekerjaan dikonfirmasi, lalu diteruskan ke saldomu setelah dipotong komisi {{ config('sejuk.commission_percent') }}%. Tim Sejuk mentransfer saldo ke rekeningmu secara berkala.</p>

    <div class="mt-7 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['hourglass', 'Ditahan Sejuk', $balances['held'], 'Menunggu konfirmasi pelanggan', 'bg-sun-soft text-sun-ink'],
            ['wallet', 'Siap dicairkan', $balances['forwarded'], 'Ditransfer tim Sejuk ke rekeningmu', 'bg-frost text-ice'],
            ['banknote', 'Sudah dicairkan', $balances['paid_out'], 'Total yang sudah ditransfer', 'bg-leaf-soft text-leaf'],
        ] as [$icon, $label, $amount, $hint, $tone])
            <x-card wire:key="bal-{{ $loop->index }}" class="p-5">
                <span class="grid size-10 place-items-center rounded-xl {{ $tone }}"><flux:icon :icon="$icon" class="size-5" /></span>
                <p class="mt-4 text-xs font-semibold text-mute">{{ $label }}</p>
                <p class="mt-1 text-xl font-extrabold tnum">{{ rupiah($amount) }}</p>
                <p class="mt-1 text-xs text-ink-soft">{{ $hint }}</p>
            </x-card>
        @endforeach
    </div>

    <div class="mt-7 grid gap-6 lg:grid-cols-[1fr_340px]">
        <x-card class="overflow-hidden">
            <div class="border-b border-line px-5 py-4 sm:px-6">
                <p class="font-bold">Riwayat dana</p>
                <p class="mt-0.5 text-xs text-mute">Nominal sudah dipotong komisi Sejuk.</p>
            </div>
            @forelse ($this->ledger as $order)
                <a wire:key="led-{{ $order->id }}" href="{{ route('mitra.orders.show', $order) }}" wire:navigate class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3.5 last:border-b-0 hover:bg-paper sm:px-6">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $order->service->name }} · {{ $order->customer->name }}</p>
                        <p class="text-xs text-mute tnum">#{{ $order->code }} · {{ ($order->completed_at ?? $order->updated_at)?->translatedFormat('j M Y') }}</p>
                    </div>
                    <x-pill :tone="$order->fund_status->tone()">{{ $order->fund_status->label() }}</x-pill>
                    <p class="w-28 text-right font-bold tnum">{{ rupiah($order->technicianPayout()) }}</p>
                </a>
            @empty
                <p class="px-6 py-10 text-center text-sm text-mute">Belum ada dana. Pendapatan muncul setelah pelanggan membayar pesanan.</p>
            @endforelse
        </x-card>

        <div class="space-y-4">
            <x-card class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <x-section-title icon="landmark" title="Rekening pencairan" />
                    @unless ($editingAccount)
                        <x-button size="sm" variant="ghost" icon="pencil" wire:click="editAccount">{{ $account ? 'Ubah' : 'Tambah' }}</x-button>
                    @endunless
                </div>
                @if ($editingAccount)
                    <form wire:submit="saveAccount" class="mt-5 space-y-4">
                        <x-form.input label="Nama bank" name="bank_name" wire:model="bank_name" placeholder="BCA" />
                        <x-form.input label="Nama pemilik rekening" name="account_name" wire:model="account_name" />
                        <x-form.input label="Nomor rekening" name="account_number" inputmode="numeric" wire:model="account_number" />
                        <div class="flex gap-2">
                            <x-button type="submit" size="sm">Simpan rekening</x-button>
                            <x-button type="button" size="sm" variant="ghost" wire:click="$set('editingAccount', false)">Batal</x-button>
                        </div>
                    </form>
                @elseif ($account)
                    <dl class="mt-5 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-mute">Bank</dt><dd class="font-semibold">{{ $account->bank_name }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-mute">Atas nama</dt><dd class="text-right font-semibold">{{ $account->account_name }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-mute">Nomor</dt><dd class="font-semibold tnum">{{ $account->maskedNumber() }}</dd></div>
                    </dl>
                @else
                    <p class="mt-4 rounded-2xl bg-sun-soft px-4 py-3 text-sm text-sun-ink">Tambahkan rekening supaya saldo bisa dicairkan.</p>
                @endif
            </x-card>

            <x-card class="p-5">
                <p class="font-bold">Pencairan</p>
                <div class="mt-3 space-y-2">
                    @forelse ($this->payouts as $payout)
                        <div wire:key="po-{{ $payout->id }}" class="flex items-center justify-between gap-3 rounded-xl bg-paper px-3 py-2.5 text-sm">
                            <div>
                                <p class="font-semibold tnum">{{ rupiah($payout->amount) }}</p>
                                <p class="text-xs text-mute">{{ $payout->transferred_at?->translatedFormat('j M Y') }} · {{ $payout->bank_name }}</p>
                            </div>
                            @if ($payout->reference)
                                <span class="text-xs text-mute">Ref. {{ $payout->reference }}</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-mute">Belum ada pencairan.</p>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
</div>
