<?php

use App\Enums\OrderStatus;
use App\Enums\TimeWindow;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Services\OrderWorkflow;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::mitra'), Title('Kirim Estimasi')] class extends Component {
    public Order $order;

    public ?int $price_min = null;

    public ?int $price_max = null;

    public string $visit_date = '';

    public string $time_window = '';

    public string $note = 'Harga final menyesuaikan hasil pemeriksaan komponen di lokasi.';

    public function mount(Order $order): void
    {
        Gate::authorize('respond', $order);

        if ($order->status !== OrderStatus::MenungguRespons) {
            $this->redirectRoute('mitra.orders.show', $order);

            return;
        }

        $this->order = $order;
        $technician = auth()->user()->technicianProfile->load('prices');
        $suggested = $technician->suggestedEstimate($order);

        $this->price_min = $suggested['min'] ?? null;
        $this->price_max = $suggested['max'] ?? null;
        $this->visit_date = $order->preferred_date->toDateString();
        $this->time_window = $order->time_window->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'price_min' => ['required', 'integer', 'min:5000', 'max:50000000'],
            'price_max' => ['required', 'integer', 'gte:price_min', 'max:50000000'],
            'visit_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays((int) config('sejuk.booking_days_ahead') + 7)->toDateString()],
            'time_window' => ['required', Rule::enum(TimeWindow::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['price_min' => 'harga minimum', 'price_max' => 'harga maksimum', 'visit_date' => 'tanggal kedatangan', 'time_window' => 'rentang waktu', 'note' => 'catatan'];
    }

    public function submit(OrderWorkflow $workflow): void
    {
        Gate::authorize('respond', $this->order);
        $validated = $this->validate();

        try {
            $workflow->submitEstimate(
                $this->order,
                auth()->user()->technicianProfile,
                (int) $validated['price_min'],
                (int) $validated['price_max'],
                CarbonImmutable::parse($validated['visit_date']),
                TimeWindow::from($validated['time_window']),
                $validated['note'] ?: null,
            );
        } catch (InvalidOrderTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
            $this->redirectRoute('mitra.orders.show', $this->order, navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: 'Penawaran berhasil dikirim ke pelanggan.');
        $this->redirectRoute('mitra.orders.show', $this->order, navigate: true);
    }
}; ?>

<div>
    <a href="{{ route('mitra.orders.show', $order) }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke pesanan
    </a>
    <h1 class="mt-6 text-3xl font-extrabold">Kirim Estimasi</h1>
    <p class="mt-2 text-ink-soft">Pesanan #{{ $order->code }} · {{ $order->service->name }} · {{ $order->brandLabel() }} {{ $order->ac_capacity->label() }}</p>

    <x-card class="mt-7 p-5 sm:p-7">
        <form wire:submit="submit" class="space-y-6">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input label="Harga minimum" name="price_min" type="number" prefix="Rp" wire:model="price_min" />
                <x-form.input label="Harga maksimum" name="price_max" type="number" prefix="Rp" wire:model="price_max" />
            </div>
            <p class="-mt-3 text-xs text-mute">Diisi otomatis dari harga acuanmu. Ubah kalau perlu.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input label="Tanggal kedatangan" name="visit_date" type="date" wire:model="visit_date" :min="now()->toDateString()" />
                <x-form.select label="Rentang waktu" name="time_window" wire:model="time_window">
                    @foreach (TimeWindow::cases() as $window)
                        <option wire:key="tw-{{ $window->value }}" value="{{ $window->value }}">{{ $window->range() }} WIB</option>
                    @endforeach
                </x-form.select>
            </div>
            <p class="-mt-3 text-xs text-mute">Pilihan pelanggan: {{ $order->preferred_date->translatedFormat('l, j F') }}, {{ $order->time_window->label() }} ({{ $order->time_window->range() }}).</p>
            <x-form.textarea label="Catatan penawaran" name="note" rows="4" wire:model="note" />
            <div class="border-t border-line pt-6">
                <x-button type="submit" size="lg" icon-trailing="arrow-right" wire:loading.attr="disabled">Kirim penawaran</x-button>
            </div>
        </form>
    </x-card>
</div>
