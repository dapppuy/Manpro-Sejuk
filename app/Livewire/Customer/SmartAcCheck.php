<?php

namespace App\Livewire\Customer;

use App\Models\CustomerUnit;
use App\Models\Diagnosis;
use App\Services\GeminiAcDiagnosisService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class SmartAcCheck extends Component
{
    use WithFileUploads;

    public ?int $customerUnitId = null;
    public string $complaintText = '';
    public $stickerPhoto;
    public $conditionPhoto;

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
            'customerUnitId' => ['nullable', 'exists:customer_units,id'],
            'complaintText' => ['required', 'string', 'min:10', 'max:1500'],
            'stickerPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'conditionPhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'complaintText.required' => 'Tuliskan keluhan AC terlebih dahulu.',
            'complaintText.min' => 'Jelaskan keluhan minimal 10 karakter agar AI bisa menganalisis lebih akurat.',
            'stickerPhoto.max' => 'Ukuran foto stiker maksimal 5 MB.',
            'conditionPhoto.max' => 'Ukuran foto kondisi AC maksimal 5 MB.',
        ]);

        $stickerPath = $this->stickerPhoto
            ? $this->stickerPhoto->store('diagnoses/stickers', 'public')
            : null;

        $conditionPath = $this->conditionPhoto
            ? $this->conditionPhoto->store('diagnoses/conditions', 'public')
            : null;

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
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function proceedToSearchTechnician()
    {
        $params = $this->diagnosis ? [
            'service' => $this->diagnosis->recommended_service_slug,
            'diagnosis_id' => $this->diagnosis->id,
            'unit_id' => $this->diagnosis->customer_unit_id,
        ] : [];

        foreach (['customer.search', 'customer.technicians', 'technicians.index', 'search'] as $routeName) {
            if (Route::has($routeName)) {
                return redirect()->route($routeName, $params);
            }
        }

        return redirect()->to('/cari-teknisi?' . http_build_query(array_filter($params)));
    }

    public function render()
    {
        $units = CustomerUnit::where('user_id', Auth::id())->get();

        return view('livewire.customer.smart-ac-check', [
            'units' => $units,
        ]);
    }
}