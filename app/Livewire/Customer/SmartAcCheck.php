<?php

namespace App\Livewire\Customer;

use App\Models\CustomerUnit;
use App\Models\Diagnosis;
use App\Services\GeminiAcDiagnosisService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

class SmartAcCheck extends Component
{
    use WithFileUploads;

    public ?int $customerUnitId = null;

    public string $complaintText = '';

    public ?TemporaryUploadedFile $stickerPhoto = null;

    public ?TemporaryUploadedFile $conditionPhoto = null;

    public ?Diagnosis $diagnosis = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        if (request()->filled('unit_id')) {
            $this->customerUnitId = (int) request('unit_id');
        }
    }

    public function updatedStickerPhoto(): void
    {
        $this->validateOnly('stickerPhoto', [
            'stickerPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'stickerPhoto.image' => 'File stiker harus berupa gambar.',
            'stickerPhoto.mimes' => 'Format foto stiker harus JPG, PNG, atau WEBP.',
            'stickerPhoto.max' => 'Ukuran foto stiker maksimal 5 MB.',
        ]);
    }

    public function updatedConditionPhoto(): void
    {
        $this->validateOnly('conditionPhoto', [
            'conditionPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'conditionPhoto.image' => 'File kondisi AC harus berupa gambar.',
            'conditionPhoto.mimes' => 'Format foto kondisi AC harus JPG, PNG, atau WEBP.',
            'conditionPhoto.max' => 'Ukuran foto kondisi AC maksimal 5 MB.',
        ]);
    }

    public function removeStickerPhoto(): void
    {
        $this->stickerPhoto = null;
    }

    public function removeConditionPhoto(): void
    {
        $this->conditionPhoto = null;
    }

    public function analyze(GeminiAcDiagnosisService $geminiService): void
    {
        $this->errorMessage = null;

        $this->validate([
            'customerUnitId' => ['nullable', Rule::exists('customer_units', 'id')->where('user_id', Auth::id())],
            'complaintText' => ['required', 'string', 'min:10', 'max:1500'],
            'stickerPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'conditionPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'complaintText.required' => 'Tuliskan keluhan AC terlebih dahulu.',
            'complaintText.min' => 'Jelaskan keluhan minimal 10 karakter agar AI bisa menganalisis lebih akurat.',
            'stickerPhoto.max' => 'Ukuran foto stiker maksimal 5 MB.',
            'conditionPhoto.max' => 'Ukuran foto kondisi AC maksimal 5 MB.',
            'customerUnitId.exists' => 'Unit AC tidak ditemukan di akunmu.',
        ]);

        $limiterKey = 'smart-check:'.Auth::id();
        $perHour = (int) config('sejuk.smart_check_per_hour');

        if (RateLimiter::tooManyAttempts($limiterKey, $perHour)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);
            $this->errorMessage = "Kamu sudah memakai {$perHour} analisis dalam satu jam terakhir. Coba lagi dalam {$minutes} menit, atau langsung pesan teknisi.";

            return;
        }

        RateLimiter::hit($limiterKey, 3600);

        $stickerPath = $this->stickerPhoto?->store('diagnoses/stickers', Diagnosis::PHOTO_DISK) ?: null;
        $conditionPath = $this->conditionPhoto?->store('diagnoses/conditions', Diagnosis::PHOTO_DISK) ?: null;

        $unitContext = [];
        if ($this->customerUnitId) {
            $unit = CustomerUnit::where('user_id', Auth::id())->find($this->customerUnitId);
            if ($unit) {
                $unitContext = [
                    'brand' => $unit->brand,
                    'capacity_pk' => $unit->capacity_pk,
                    'ac_type' => $unit->ac_type,
                    'last_serviced_at' => optional($unit->last_serviced_at)->format('Y-m-d'),
                ];
            }
        }

        try {
            $result = $geminiService->analyze(
                $this->complaintText,
                $stickerPath,
                $conditionPath,
                $unitContext
            );

            $parsed = $result['parsed'];

            $this->diagnosis = Diagnosis::create([
                'user_id' => Auth::id(),
                'customer_unit_id' => $this->customerUnitId,
                'complaint_text' => $this->complaintText,
                'sticker_photo_path' => $stickerPath,
                'condition_photo_path' => $conditionPath,
                'detected_unit_info' => $parsed['detected_unit_info'] ?? null,
                'possible_causes' => $parsed['possible_causes'] ?? [],
                'confidence_level' => $parsed['confidence_level'] ?? 'sedang',
                'confidence_score' => $parsed['confidence_score'] ?? 60,
                'urgency_level' => $parsed['urgency_level'] ?? 'sedang',
                'recommended_service_slug' => $parsed['recommended_service_slug'] ?? 'perbaikan',
                'preliminary_advice' => $parsed['preliminary_advice'] ?? null,
                'raw_ai_response' => $result['raw'],
            ]);
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            report($e);
            $this->errorMessage = 'Cek AC Pintar sedang bermasalah. Kamu tetap bisa langsung memesan teknisi.';
        }
    }

    public function proceedToSearchTechnician(): void
    {
        $params = $this->diagnosis ? [
            'service' => $this->diagnosis->recommended_service_slug,
            'diagnosis_id' => $this->diagnosis->id,
            'unit_id' => $this->diagnosis->customer_unit_id,
        ] : [];

        $this->redirectRoute('search', $params);
    }

    public function render(): View
    {
        $units = CustomerUnit::where('user_id', Auth::id())->get();

        return view('livewire.customer.smart-ac-check', [
            'units' => $units,
        ]);
    }
}
