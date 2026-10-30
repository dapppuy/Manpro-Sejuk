<?php

use App\Actions\Payouts\RecordPayout;
use App\Enums\FundStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\Payout;
use App\Models\TechnicianProfile;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin'), Title('Dana & pencairan')] class extends Component {
    /**
     * Transfer references typed per technician.
     *
     * @var array<int, string>
     */
    public array $references = [];

    /**
     * @return array{held: int, forwarded: int, paid_out: int, refund: int}
     */
    #[Computed]
    public function totals(): array
    {
        $sum = fn (FundStatus $status) => (int) Order::where('fund_status', $status)->sum('final_total');

        return [
            'held' => $sum(FundStatus::Ditahan),
            'forwarded' => (int) Order::where('fund_status', FundStatus::Diteruskan)->get(['final_total', 'commission_amount'])->sum(fn (Order $order) => $order->technicianPayout()),
            'paid_out' => (int) Payout::sum('amount'),
            'refund' => $sum(FundStatus::Dikembalikan),
        ];
    }

    /**
     * Technicians with forwarded earnings not yet transferred.
     *
     * @return SupportCollection<int, array{technician: TechnicianProfile, amount: int, orders: int}>
     */
    #[Computed]
    public function pending(): SupportCollection
    {
        $orders = Order::where('fund_status', FundStatus::Diteruskan)->whereNull('payout_id')->get(['id', 'technician_id', 'final_total', 'commission_amount']);
        $technicians = TechnicianProfile::with(['user', 'payoutAccount'])->whereKey($orders->pluck('technician_id')->unique())->get()->keyBy('id');

        return $orders->groupBy('technician_id')->map(fn (Collection $group, int $technicianId) => [
            'technician' => $technicians[$technicianId],
            'amount' => (int) $group->sum(fn (Order $order) => $order->technicianPayout()),
            'orders' => $group->count(),
        ])->sortByDesc('amount')->values();
    }

    /**
     * Paid orders the admin cancelled, which need a manual refund to the customer.
     *
     * @return Collection<int, Order>
     */
    #[Computed]
    public function refunds(): Collection
    {
        return Order::where('fund_status', FundStatus::Dikembalikan)->with('customer')->latest('updated_at')->limit(20)->get();
    }

    /**
     * @return Collection<int, Payout>
     */
    #[Computed]
    public function payouts(): Collection
    {
        return Payout::with('technician')->latest('transferred_at')->limit(20)->get();
    }

    public function markTransferred(int $technicianId, RecordPayout $recordPayout): void
    {
        $technician = TechnicianProfile::with('payoutAccount')->findOrFail($technicianId);
        $reference = trim($this->references[$technicianId] ?? '') ?: null;

        try {
            $payout = $recordPayout->handle($technician, auth()->user(), $reference);
        } catch (InvalidOrderTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->references[$technicianId], $this->pending, $this->payouts, $this->totals);
        Flux::toast(variant: 'success', text: rupiah($payout->amount).' ke '.$technician->business_name.' ditandai sudah ditransfer.');
    }
}; ?>

@php
    $totals = $this->totals;
@endphp

<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Dana & pencairan</h1>
        <p class="mt-1 text-ink-soft">Dana pelanggan ditahan sampai pekerjaan dikonfirmasi, diteruskan ke saldo teknisi setelah komisi {{ config('sejuk.commission_percent') }}%, lalu ditransfer manual ke rekening teknisi.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([['Ditahan Sejuk', $totals['held']], ['Siap dicairkan', $totals['forwarded']], ['Sudah dicairkan', $totals['paid_out']], ['Perlu dikembalikan', $totals['refund']]] as [$label, $amount])
            <x-card wire:key="t-{{ $loop->index }}" class="p-5">
                <p class="text-sm text-mute">{{ $label }}</p>
                <p class="mt-2 text-2xl font-extrabold tnum">{{ rupiah($amount) }}</p>
            </x-card>
        @endforeach
    </div>

    <x-card class="overflow-hidden">
        <div class="border-b border-line px-5 py-4">
            <p class="font-bold">Siap dicairkan</p>
            <p class="mt-0.5 text-xs text-mute">Transfer ke rekening teknisi, lalu tandai di sini. Semua pesanan yang sudah diteruskan ikut dicairkan.</p>
        </div>
        @forelse ($this->pending as $row)
            @php $technician = $row['technician']; $account = $technician->payoutAccount; @endphp
            <div wire:key="p-{{ $technician->id }}" class="flex flex-wrap items-center gap-4 border-b border-line px-5 py-4 last:border-b-0">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $technician->business_name }}</p>
                    <p class="text-xs text-mute">
                        {{ $row['orders'] }} pesanan ·
                        @if ($account)
                            {{ $account->bank_name }} {{ $account->account_number }} a.n. {{ $account->account_name }}
                        @else
                            <span class="text-coral">Belum mengisi rekening</span>
                        @endif
                    </p>
                </div>
                <p class="text-lg font-extrabold tnum">{{ rupiah($row['amount']) }}</p>
                <div class="flex w-full gap-2 sm:w-auto">
                    <input type="text" wire:model="references.{{ $technician->id }}" placeholder="No. referensi transfer" class="h-9 min-w-0 flex-1 rounded-full bg-white px-3 text-xs ring-1 ring-line outline-none focus:ring-ice sm:w-44" @disabled(! $account) />
                    <x-button size="sm" wire:click="markTransferred({{ $technician->id }})" wire:confirm="Tandai {{ rupiah($row['amount']) }} sudah ditransfer ke {{ $technician->business_name }}?" :disabled="! $account">Sudah ditransfer</x-button>
                </div>
            </div>
        @empty
            <p class="px-5 py-10 text-center text-sm text-mute">Tidak ada saldo yang menunggu pencairan.</p>
        @endforelse
    </x-card>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card class="overflow-hidden">
            <p class="border-b border-line px-5 py-4 font-bold">Riwayat pencairan</p>
            @forelse ($this->payouts as $payout)
                <div wire:key="h-{{ $payout->id }}" class="flex justify-between gap-3 border-b border-line px-5 py-3 text-sm last:border-b-0">
                    <div>
                        <p class="font-semibold">{{ $payout->technician->business_name }}</p>
                        <p class="text-xs text-mute">{{ $payout->transferred_at?->translatedFormat('j M Y, H.i') }} @if ($payout->reference) · Ref. {{ $payout->reference }} @endif</p>
                    </div>
                    <span class="font-bold tnum">{{ rupiah($payout->amount) }}</span>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-mute">Belum ada pencairan.</p>
            @endforelse
        </x-card>

        <x-card class="overflow-hidden">
            <p class="border-b border-line px-5 py-4 font-bold">Pengembalian dana ke pelanggan</p>
            @forelse ($this->refunds as $order)
                <a wire:key="r-{{ $order->id }}" href="{{ route('admin.orders.show', $order) }}" wire:navigate class="flex justify-between gap-3 border-b border-line px-5 py-3 text-sm last:border-b-0 hover:bg-paper">
                    <div>
                        <p class="font-semibold">{{ $order->customer->name }} · <span class="tnum">#{{ $order->code }}</span></p>
                        <p class="text-xs text-mute">{{ $order->cancelled_reason }}</p>
                    </div>
                    <span class="font-bold tnum">{{ rupiah($order->final_total) }}</span>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-mute">Tidak ada dana yang perlu dikembalikan.</p>
            @endforelse
        </x-card>
    </div>
</div>
