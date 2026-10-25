<?php

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Services\OrderWorkflow;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts::mitra'), Title('Upload Bukti Kerja')] class extends Component {
    use WithFileUploads;

    public Order $order;

    /** @var TemporaryUploadedFile|null */
    public $before = null;

    /** @var TemporaryUploadedFile|null */
    public $after = null;

    public string $note = '';

    public function mount(Order $order): void
    {
        Gate::authorize('respond', $order);

        if (! in_array($order->status, [OrderStatus::Dikerjakan, OrderStatus::PerbaikanUlang], true)) {
            $this->redirectRoute('mitra.orders.show', $order);

            return;
        }

        $this->order = $order->load(['service', 'workProofs']);
    }

    public function updatedBefore(): void
    {
        $this->validateOnly('before');
    }

    public function updatedAfter(): void
    {
        $this->validateOnly('after');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'before' => ['required', 'image', 'max:10240'],
            'after' => ['required', 'image', 'max:10240'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['before' => 'foto sebelum', 'after' => 'foto sesudah', 'note' => 'catatan'];
    }

    public function submit(OrderWorkflow $workflow): void
    {
        Gate::authorize('respond', $this->order);
        $validated = $this->validate();

        try {
            $workflow->submitProof($this->order, auth()->user()->technicianProfile, $this->before, $this->after, $validated['note'] ?: null);
        } catch (InvalidOrderTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Pekerjaan ditandai selesai. Pelanggan diminta memeriksa hasilnya.');
        $this->redirectRoute('mitra.orders.show', $this->order, navigate: true);
    }
}; ?>

@php
    $rejected = $order->workProofs->whereNotNull('rejected_at')->sortByDesc('round')->first();
@endphp

<div>
    <a href="{{ route('mitra.orders.show', $order) }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-ink-soft hover:text-ink">
        <flux:icon.arrow-left class="size-4" /> Kembali ke pesanan
    </a>
    <h1 class="mt-6 text-3xl font-extrabold">Upload Bukti Kerja</h1>
    <p class="mt-2 text-ink-soft">Unggah foto sebelum dan sesudah {{ $order->rework_count > 0 ? 'perbaikan ulang' : 'perbaikan' }} agar pelanggan dapat memeriksa hasil.</p>

    @if ($order->status === OrderStatus::PerbaikanUlang && $rejected)
        <div class="mt-6 flex items-start gap-3 rounded-2xl bg-sun-soft p-5 text-sm text-sun-ink ring-1 ring-sun/40">
            <flux:icon.rotate-ccw class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-bold">Perbaikan ulang ke-{{ $order->rework_count }}, tanpa biaya tambahan</p>
                <p class="mt-1">{{ $rejected->complaint ? 'Keluhan pelanggan: “'.$rejected->complaint.'”' : 'Pelanggan menyatakan AC masih bermasalah.' }}</p>
            </div>
        </div>
    @endif

    <x-card class="mt-7 p-5 sm:p-7">
        <form wire:submit="submit" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['before' => 'Foto Sebelum', 'after' => 'Foto Sesudah'] as $field => $label)
                    @php $file = $this->{$field}; @endphp
                    <div wire:key="upload-{{ $field }}">
                        <label @class([
                            'relative flex aspect-[4/3] cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed text-center',
                            'border-coral/50 bg-coral-soft/40' => $errors->has($field),
                            'border-line bg-paper hover:border-ice' => ! $errors->has($field),
                        ])>
                            @if ($file && ! $errors->has($field) && $file->isPreviewable())
                                <img src="{{ $file->temporaryUrl() }}" alt="Pratinjau {{ strtolower($label) }}" class="absolute inset-0 size-full object-cover" />
                                <span class="absolute top-3 left-3 rounded-full bg-ink/75 px-3 py-1 text-xs font-bold text-white">{{ $label }}</span>
                                <span class="absolute right-3 bottom-3 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-ink">Ganti foto</span>
                            @else
                                <span wire:loading.remove wire:target="{{ $field }}" class="flex flex-col items-center">
                                    <flux:icon.image-plus class="size-8 text-ice" />
                                    <span class="mt-3 font-bold">{{ $label }}</span>
                                    <span class="mt-1 text-xs text-mute">Klik untuk pilih foto</span>
                                </span>
                                <flux:icon.loading wire:loading wire:target="{{ $field }}" class="size-8 text-ice" />
                            @endif
                            <input type="file" accept="image/*" capture="environment" wire:model="{{ $field }}" class="sr-only" />
                        </label>
                        @error($field) <p class="mt-1.5 text-xs font-medium text-coral">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>

            <x-form.textarea label="Catatan hasil pekerjaan" name="note" rows="4" wire:model="note" placeholder="Jelaskan pekerjaan yang dilakukan dan kondisi AC setelah servis." />

            <div class="flex items-start gap-3 rounded-2xl bg-leaf-soft p-4 text-sm text-leaf">
                <flux:icon.package-check class="mt-0.5 size-5 shrink-0" />
                <p>Setelah ditandai selesai, pelanggan akan menerima notifikasi untuk memeriksa bukti dan mengonfirmasi hasil pekerjaan. Tanpa konfirmasi dalam {{ config('sejuk.auto_complete_hours') }} jam, pesanan selesai otomatis dan dana diteruskan.</p>
            </div>

            <div class="border-t border-line pt-6">
                <x-button type="submit" size="lg" icon-trailing="arrow-right" :disabled="! $before || ! $after" wire:loading.attr="disabled" wire:target="submit,before,after">Tandai Selesai</x-button>
            </div>
        </form>
    </x-card>
</div>
