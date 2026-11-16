<?php

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\UnitServiceLog;
use App\Services\OrderWorkflow;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

    public string $findings = '';

    public string $actions = '';

    /**
     * What the technician actually found, compared with the Cek AC Pintar estimate (TK-18).
     */
    public string $actualCause = '';

    public string $diagnosisAccurate = '';

    /**
     * Months until the next recommended service; 0 means no recommendation.
     */
    public int $nextServiceMonths = 3;

    public function mount(Order $order): void
    {
        Gate::authorize('respond', $order);

        if (! in_array($order->status, [OrderStatus::Dikerjakan, OrderStatus::PerbaikanUlang], true)) {
            $this->redirectRoute('mitra.orders.show', $order);

            return;
        }

        $this->order = $order->load(['service', 'workProofs', 'customerUnit', 'diagnosis']);
        $this->actualCause = (string) $this->order->diagnosis?->actual_cause_by_technician;
        $this->diagnosisAccurate = match ($this->order->diagnosis?->is_accurate) {
            true => 'ya',
            false => 'tidak',
            null => '',
        };

        $existingLog = UnitServiceLog::where('order_id', $order->id)->first();

        if ($existingLog !== null) {
            $this->findings = (string) $existingLog->inspection_findings;
            $this->actions = $existingLog->actions_taken;
        }
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
            'findings' => ['nullable', 'string', 'max:1000'],
            'actions' => [Rule::requiredIf($this->order->customer_unit_id !== null), 'nullable', 'string', 'max:1000'],
            'nextServiceMonths' => ['integer', Rule::in([0, 1, 3, 6, 12])],
            'actualCause' => [Rule::requiredIf($this->order->diagnosis_id !== null), 'nullable', 'string', 'max:1000'],
            'diagnosisAccurate' => [Rule::requiredIf($this->order->diagnosis_id !== null), 'nullable', Rule::in(['ya', 'tidak'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['before' => 'foto sebelum', 'after' => 'foto sesudah', 'note' => 'catatan', 'findings' => 'temuan pemeriksaan', 'actions' => 'tindakan yang dilakukan', 'actualCause' => 'penyebab sebenarnya', 'diagnosisAccurate' => 'kesesuaian perkiraan AI'];
    }

    public function submit(OrderWorkflow $workflow): void
    {
        Gate::authorize('respond', $this->order);
        $validated = $this->validate();

        try {
            $unitRecord = $this->order->customer_unit_id === null ? null : [
                'findings' => $validated['findings'] ?: null,
                'actions' => $validated['actions'],
                'next_service_date' => $this->nextServiceMonths > 0 ? today()->addMonths($this->nextServiceMonths)->toDateString() : null,
            ];

            $workflow->submitProof($this->order, auth()->user()->technicianProfile, $this->before, $this->after, $validated['note'] ?: null, $unitRecord);

            $this->order->diagnosis?->update([
                'actual_cause_by_technician' => $validated['actualCause'],
                'is_accurate' => $validated['diagnosisAccurate'] === 'ya',
            ]);
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

            @if ($order->diagnosis)
                <div class="space-y-4 rounded-2xl bg-paper p-4 ring-1 ring-line sm:p-5">
                    <div class="flex items-start gap-3">
                        <flux:icon.sparkles class="mt-0.5 size-5 shrink-0 text-ice" />
                        <div>
                            <p class="font-bold">Bandingkan dengan Cek AC Pintar</p>
                            <p class="text-sm text-ink-soft">Perkiraan AI: {{ collect($order->diagnosis->possible_causes ?? [])->pluck('title')->take(2)->implode(', ') ?: '–' }}. Catatanmu membantu Sejuk menilai ketepatan AI.</p>
                        </div>
                    </div>
                    <x-form.textarea label="Penyebab sebenarnya" name="actualCause" rows="2" wire:model="actualCause" placeholder="Contoh: kapasitor kipas outdoor lemah, bukan freon habis." required />
                    <x-form.select label="Apakah perkiraan AI sesuai?" name="diagnosisAccurate" wire:model="diagnosisAccurate" required>
                        <option value="">Pilih</option>
                        <option value="ya">Ya, sesuai atau mendekati</option>
                        <option value="tidak">Tidak sesuai</option>
                    </x-form.select>
                </div>
            @endif

            @if ($order->customerUnit)
                <div class="space-y-4 rounded-2xl bg-frost/60 p-4 ring-1 ring-ice/20 sm:p-5">
                    <div class="flex items-start gap-3">
                        <flux:icon.qr-code class="mt-0.5 size-5 shrink-0 text-ice" />
                        <div>
                            <p class="font-bold">Catatan Riwayat AC · {{ $order->customerUnit->name }}{{ $order->customerUnit->room_label ? ' ('.$order->customerUnit->room_label.')' : '' }}</p>
                            <p class="text-sm text-ink-soft">Masuk ke riwayat unit ini dan bisa dilihat teknisi berikutnya lewat stiker QR.</p>
                        </div>
                    </div>
                    <x-form.textarea label="Temuan pemeriksaan (opsional)" name="findings" rows="2" wire:model="findings" placeholder="Contoh: filter berdebu, tekanan freon normal 70 psi." />
                    <x-form.textarea label="Tindakan yang dilakukan" name="actions" rows="2" wire:model="actions" placeholder="Contoh: cuci indoor dan outdoor, bersihkan saluran pembuangan." required />
                    <x-form.select label="Servis berikutnya disarankan" name="nextServiceMonths" wire:model="nextServiceMonths">
                        <option value="1">1 bulan lagi</option>
                        <option value="3">3 bulan lagi</option>
                        <option value="6">6 bulan lagi</option>
                        <option value="12">12 bulan lagi</option>
                        <option value="0">Tidak perlu dijadwalkan</option>
                    </x-form.select>
                </div>
            @endif

            <div class="flex items-start gap-3 rounded-2xl bg-leaf-soft p-4 text-sm text-leaf">
                <flux:icon.package-check class="mt-0.5 size-5 shrink-0" />
                <p>Setelah ditandai selesai, pelanggan akan menerima notifikasi untuk memeriksa bukti dan mengonfirmasi hasil pekerjaan. Tanpa konfirmasi dalam {{ config('sejuk.auto_complete_hours') }} jam, pesanan selesai otomatis dan dana diteruskan.</p>
            </div>

            <div class="border-t border-line pt-6">
                <x-button type="submit" size="lg" icon-trailing="arrow-right" :disabled="! $before || ! $after" wire:target="submit,before,after">Tandai Selesai</x-button>
            </div>
        </form>
    </x-card>
</div>
