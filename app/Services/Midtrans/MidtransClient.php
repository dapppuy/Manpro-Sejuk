<?php

namespace App\Services\Midtrans;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the Midtrans Core API (charge, status, cancel) and notification signatures.
 */
class MidtransClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.midtrans.server_key'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws MidtransException
     */
    public function charge(array $payload): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post('/v2/charge', $payload), expectSuccess: true);
    }

    /**
     * Current transaction state straight from Midtrans, used to reconcile missed webhooks.
     *
     * @return array<string, mixed>
     *
     * @throws MidtransException
     */
    public function status(string $orderId): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get('/v2/'.rawurlencode($orderId).'/status'));
    }

    /**
     * Cancel a pending transaction so it can no longer be paid. Failures are ignored by callers.
     *
     * @return array<string, mixed>
     *
     * @throws MidtransException
     */
    public function cancel(string $orderId): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post('/v2/'.rawurlencode($orderId).'/cancel'));
    }

    /**
     * @param  array<string, mixed>  $notification
     */
    public function hasValidSignature(array $notification): bool
    {
        $expected = hash('sha512',
            ($notification['order_id'] ?? '').($notification['status_code'] ?? '').($notification['gross_amount'] ?? '').config('services.midtrans.server_key'),
        );

        return is_string($notification['signature_key'] ?? null) && hash_equals($expected, $notification['signature_key']);
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     *
     * @throws MidtransException
     */
    private function send(callable $call, bool $expectSuccess = false): array
    {
        if (! $this->isConfigured()) {
            throw new MidtransException('Pembayaran belum dikonfigurasi. Isi MIDTRANS_SERVER_KEY di .env.');
        }

        try {
            $response = $call($this->client())->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new MidtransException('Layanan pembayaran sedang tidak bisa dihubungi. Coba lagi sebentar.', previous: $exception);
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $statusCode = (string) ($body['status_code'] ?? '');

        if ($expectSuccess && ! in_array($statusCode, ['200', '201'], true)) {
            throw new MidtransException('Pembayaran ditolak: '.($body['status_message'] ?? 'kesalahan tidak dikenal').'.');
        }

        return $body;
    }

    private function client(): PendingRequest
    {
        $baseUrl = config('services.midtrans.is_production') ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';

        return Http::baseUrl($baseUrl)
            ->withBasicAuth((string) config('services.midtrans.server_key'), '')
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
    }
}
