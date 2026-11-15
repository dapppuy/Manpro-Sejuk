<?php

namespace App\Services;

use App\Models\Diagnosis;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GeminiAcDiagnosisService
{
    /**
     * @param  array<string, mixed>  $knownUnitContext
     * @return array{parsed: array<mixed>, raw: mixed, model_used: string}
     */
    public function analyze(
        string $complaintText,
        ?string $stickerPhotoPath = null,
        ?string $conditionPhotoPath = null,
        array $knownUnitContext = []
    ): array {
        @set_time_limit(90);

        $apiKey = config('services.gemini.api_key');
        $primaryModel = config('services.gemini.model', 'gemini-flash-lite-latest');
        $baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');

        if (empty($apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY belum diisi pada file .env.');
        }

        $parts = [];

        $prompt = "Kamu adalah asisten diagnostik awal teknisi AC profesional untuk platform Sejuk.\n"
            ."Analisis keluhan pelanggan beserta foto stiker spesifikasi dan/atau foto kondisi unit AC jika dilampirkan.\n"
            ."Aturan wajib:\n"
            ."1. Hasil ini adalah PERKIRAAN AWAL untuk membantu pelanggan memahami kemungkinan gangguan dan memberi konteks ke teknisi, BUKAN pengganti pemeriksaan fisik teknisi.\n"
            ."2. Jika foto stiker spesifikasi dilampirkan, ekstrak merek, kapasitas PK, dan tipe refrigeran/freon. Jika tidak ada foto stiker, isi dengan 'Tidak terdeteksi' atau gunakan data unit tersimpan.\n"
            ."3. Pilih kategori layanan yang paling sesuai dari salah satu slug berikut: 'cuci-ac', 'perbaikan', 'bongkar-pasang', atau 'servis-berkala'.\n"
            ."4. Gunakan bahasa Indonesia yang jelas, ringkas, dan mudah dipahami orang awam.\n\n"
            ."Keluhan Pelanggan: {$complaintText}\n";

        if (! empty($knownUnitContext)) {
            $prompt .= 'Data Unit Tersimpan: '.json_encode($knownUnitContext, JSON_UNESCAPED_UNICODE)."\n";
        }

        $parts[] = ['text' => $prompt];

        if ($stickerPhotoPath && Storage::disk(Diagnosis::PHOTO_DISK)->exists($stickerPhotoPath)) {
            $parts[] = ['text' => 'Gambar 1: Foto stiker spesifikasi teknis unit AC.'];
            $parts[] = $this->encodeImagePart($stickerPhotoPath);
        }

        if ($conditionPhotoPath && Storage::disk(Diagnosis::PHOTO_DISK)->exists($conditionPhotoPath)) {
            $parts[] = ['text' => 'Gambar 2: Foto kondisi visual unit AC saat ini.'];
            $parts[] = $this->encodeImagePart($conditionPhotoPath);
        }

        $candidateModels = $this->getCandidateModels($baseUrl, $apiKey, $primaryModel);
        $lastErrorDetail = 'Semua model Gemini sedang sibuk atau timeout.';

        foreach ($candidateModels as $modelName) {
            $payload = $this->buildPayload($parts, $modelName);
            $endpoint = "{$baseUrl}/models/{$modelName}:generateContent?key={$apiKey}";

            try {
                $response = Http::withOptions([
                    'force_ip_resolve' => 'v4',
                ])
                    ->connectTimeout(6)
                    ->timeout(12)
                    ->acceptJson()
                    ->post($endpoint, $payload);
            } catch (ConnectionException $e) {
                Log::warning("Gemini timeout pada model {$modelName}, mencoba model berikutnya.", [
                    'message' => $e->getMessage(),
                ]);
                $lastErrorDetail = "Timeout pada {$modelName}: {$e->getMessage()}";

                continue;
            }

            if ($response->successful()) {
                $body = $response->json();
                $jsonText = data_get($body, 'candidates.0.content.parts.0.text');
                $parsed = json_decode((string) $jsonText, true);

                if (is_array($parsed)) {
                    return [
                        'parsed' => $parsed,
                        'raw' => $body,
                        'model_used' => $modelName,
                    ];
                }
            }

            $status = $response->status();
            $apiMessage = data_get($response->json(), 'error.message', $response->body());
            $lastErrorDetail = "Gemini Error ({$status}) pada {$modelName}: {$apiMessage}";

            Log::warning("Gemini gagal pada model {$modelName}", [
                'status' => $status,
                'body' => $response->body(),
            ]);

            // Lanjut ke model cadangan jika overload (503), rate limit (429), not found (404), atau bad request parameter (400)
            if (! in_array($status, [400, 404, 429, 503], true)) {
                break;
            }
        }

        Log::error('Gemini AC Diagnosis API Error (Semua kandidat gagal)', [
            'detail' => $lastErrorDetail,
        ]);

        if (config('app.debug')) {
            throw new RuntimeException($lastErrorDetail);
        }

        throw new RuntimeException('Layanan Cek AC Pintar sedang sibuk. Kamu tetap bisa melanjutkan pemesanan teknisi secara langsung.');
    }

    /**
     * @return list<string>
     */
    private function getCandidateModels(string $baseUrl, string $apiKey, string $primaryModel): array
    {
        $availableFlashModels = Cache::remember('gemini_available_flash_models_v2', 3600, function () use ($baseUrl, $apiKey) {
            try {
                $res = Http::withOptions(['force_ip_resolve' => 'v4'])
                    ->connectTimeout(5)
                    ->timeout(8)
                    ->get("{$baseUrl}/models?key={$apiKey}");

                if ($res->failed()) {
                    return [];
                }

                $models = $res->json('models');
                $names = [];

                foreach (is_array($models) ? $models : [] as $model) {
                    $name = is_array($model) && is_string($model['name'] ?? null) ? $model['name'] : '';
                    $methods = is_array($model) && is_array($model['supportedGenerationMethods'] ?? null) ? $model['supportedGenerationMethods'] : [];

                    if (in_array('generateContent', $methods, true)
                        && str_contains($name, 'flash')
                        && ! str_contains($name, 'image')
                        && ! str_contains($name, 'tts')
                        && ! str_contains($name, 'audio')) {
                        $names[] = str_replace('models/', '', $name);
                    }
                }

                rsort($names);

                return $names;
            } catch (\Throwable) {
                return [];
            }
        });

        return array_slice(array_values(array_unique([$primaryModel, ...$availableFlashModels])), 0, 5);
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @return array<string, mixed>
     */
    private function buildPayload(array $parts, string $modelName): array
    {
        $generationConfig = [
            'temperature' => 0.3,
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'detected_unit_info' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'brand' => ['type' => 'STRING'],
                            'capacity_pk' => ['type' => 'STRING'],
                            'refrigerant' => ['type' => 'STRING'],
                        ],
                        'required' => ['brand', 'capacity_pk', 'refrigerant'],
                    ],
                    'possible_causes' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'title' => ['type' => 'STRING'],
                                'explanation' => ['type' => 'STRING'],
                                'likelihood' => ['type' => 'STRING', 'enum' => ['tinggi', 'sedang', 'rendah']],
                            ],
                            'required' => ['title', 'explanation', 'likelihood'],
                        ],
                    ],
                    'confidence_level' => [
                        'type' => 'STRING',
                        'enum' => ['rendah', 'sedang', 'tinggi'],
                    ],
                    'confidence_score' => [
                        'type' => 'INTEGER',
                    ],
                    'urgency_level' => [
                        'type' => 'STRING',
                        'enum' => ['rendah', 'sedang', 'tinggi', 'darurat'],
                    ],
                    'recommended_service_slug' => [
                        'type' => 'STRING',
                        'enum' => ['cuci-ac', 'perbaikan', 'bongkar-pasang', 'servis-berkala'],
                    ],
                    'preliminary_advice' => [
                        'type' => 'STRING',
                    ],
                ],
                'required' => [
                    'detected_unit_info',
                    'possible_causes',
                    'confidence_level',
                    'confidence_score',
                    'urgency_level',
                    'recommended_service_slug',
                    'preliminary_advice',
                ],
            ],
        ];

        if (str_contains($modelName, '2.5') || str_contains($modelName, '3.')) {
            $generationConfig['thinkingConfig'] = [
                'thinkingBudget' => 0,
            ];
        }

        return [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => $generationConfig,
        ];
    }

    /**
     * @return array{inlineData: array{mimeType: string, data: string}}
     */
    private function encodeImagePart(string $storagePath): array
    {
        $disk = Storage::disk(Diagnosis::PHOTO_DISK);
        $fullPath = $disk->path($storagePath);
        $bytes = $disk->get($storagePath);
        $mimeType = @mime_content_type($fullPath) ?: 'image/jpeg';

        if (function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring($bytes);
            if ($image !== false) {
                $width = imagesx($image);
                if ($width > 1024) {
                    $resized = imagescale($image, 1024);
                    if ($resized !== false) {
                        $image = $resized;
                    }
                }

                ob_start();
                imagejpeg($image, null, 80);
                $compressedBytes = ob_get_clean();
                unset($image);

                if (! empty($compressedBytes)) {
                    $bytes = $compressedBytes;
                    $mimeType = 'image/jpeg';
                }
            }
        }

        return [
            'inlineData' => [
                'mimeType' => $mimeType,
                'data' => base64_encode($bytes),
            ],
        ];
    }
}
