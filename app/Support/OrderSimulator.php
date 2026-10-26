<?php

namespace App\Support;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Plays the technician and payment gateway for an order during local development, through the
 * real workflow, so the customer journey can be tried end to end from one account.
 */
class OrderSimulator
{
    public function __construct(private OrderWorkflow $workflow) {}

    public static function isEnabled(): bool
    {
        return app()->isLocal() && (bool) config('sejuk.simulation');
    }

    /**
     * Actions that make sense for the order's current status: key => button label.
     *
     * @return array<string, string>
     */
    public function actionsFor(Order $order): array
    {
        return match ($order->status) {
            OrderStatus::MenungguRespons => [
                'sendEstimate' => 'Teknisi kirim estimasi',
                'declineRequest' => 'Teknisi menolak',
                'expireResponse' => 'Lewati batas 15 menit',
            ],
            OrderStatus::Terjadwal => [
                'sendFinalCost' => 'Teknisi kirim biaya final (sesuai estimasi)',
                'sendFinalCostWithExtras' => 'Teknisi kirim biaya final + tambahan',
            ],
            OrderStatus::MenungguPembayaran => [
                'payNow' => 'Pembayaran berhasil',
                'failPayment' => 'Pembayaran gagal',
                'expirePayment' => 'Waktu bayar habis',
            ],
            OrderStatus::Dikerjakan, OrderStatus::PerbaikanUlang => [
                'uploadProof' => 'Teknisi unggah bukti kerja',
            ],
            OrderStatus::MenungguKonfirmasi => [
                'autoComplete' => 'Lewati 24 jam (selesai otomatis)',
            ],
            default => [],
        };
    }

    public function run(Order $order, string $action): void
    {
        if (! array_key_exists($action, $this->actionsFor($order))) {
            throw new InvalidOrderTransition('Langkah simulasi ini tidak cocok dengan status pesanan sekarang.');
        }

        $this->{$action}($order);
    }

    private function sendEstimate(Order $order): void
    {
        $technician = $order->technician()->with('prices')->firstOrFail();
        $range = $technician->suggestedEstimate($order) ?? ['min' => 100000, 'max' => 150000];

        $this->workflow->submitEstimate(
            $order,
            $technician,
            $range['min'],
            $range['max'],
            CarbonImmutable::parse($order->preferred_date),
            $order->time_window,
            'Harga final menyesuaikan hasil pemeriksaan komponen di lokasi. (simulasi)',
        );
    }

    private function declineRequest(Order $order): void
    {
        $this->workflow->releaseToBackup($order, OfferStatus::Ditolak, $order->technician?->user, 'Ditolak teknisi (simulasi)');
    }

    private function expireResponse(Order $order): void
    {
        $order->update(['response_deadline' => now()->subSecond()]);
        $this->workflow->expireIfOverdue($order->refresh());
    }

    private function sendFinalCost(Order $order): void
    {
        $this->workflow->submitFinalCost($order, $order->technician()->firstOrFail(), [
            $this->baseLine($order),
        ]);
    }

    private function sendFinalCostWithExtras(Order $order): void
    {
        $this->workflow->submitFinalCost($order, $order->technician()->firstOrFail(), [
            $this->baseLine($order),
            ['label' => 'Isi ulang freon R32', 'amount' => 150000, 'is_additional' => true],
            ['label' => 'Ganti kapasitor 35 µF', 'amount' => 85000, 'is_additional' => true],
        ]);
    }

    /**
     * Settle the order as if Midtrans confirmed a QRIS payment.
     */
    private function payNow(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->payments()->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Cancelled]);
            $payment = $this->simulatedPayment($order, PaymentStatus::Paid);
            $payment->update(['paid_at' => now()]);

            $this->workflow->markPaid($order, 'Pembayaran QRIS diterima (simulasi), dana ditahan Sejuk');
        });
    }

    private function failPayment(Order $order): void
    {
        $order->payments()->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Cancelled]);
        $this->simulatedPayment($order, PaymentStatus::Failed);
    }

    private function expirePayment(Order $order): void
    {
        $order->payments()->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Cancelled]);
        $this->simulatedPayment($order, PaymentStatus::Expired);
    }

    private function uploadProof(Order $order): void
    {
        $this->workflow->submitProof(
            $order,
            $order->technician()->firstOrFail(),
            $this->placeholderPhoto('sebelum'),
            $this->placeholderPhoto('sesudah'),
            'AC dibersihkan, freon dicek, dan unit diuji menyala dingin. (simulasi)',
        );
    }

    private function autoComplete(Order $order): void
    {
        $order->update(['proof_submitted_at' => now()->subHours((int) config('sejuk.auto_complete_hours') + 1)]);
        $this->workflow->autoCompleteOverdue();
    }

    /**
     * @return array{label: string, amount: int, is_additional: bool}
     */
    private function baseLine(Order $order): array
    {
        $midpoint = (int) (round((($order->estimate_min ?? 100000) + ($order->estimate_max ?? 150000)) / 2 / 5000) * 5000);

        return ['label' => $order->service->name.' · '.$order->ac_capacity->label(), 'amount' => $midpoint, 'is_additional' => false];
    }

    private function simulatedPayment(Order $order, PaymentStatus $status): Payment
    {
        $payment = $order->payments()->create([
            'method' => PaymentMethod::Qris,
            'amount' => (int) $order->final_total,
            'status' => $status,
            'expires_at' => now(),
        ]);
        $payment->update(['gateway_ref' => $order->code.'-SIM'.$payment->id]);

        return $payment;
    }

    private function placeholderPhoto(string $side): UploadedFile
    {
        return new UploadedFile(resource_path("simulation/{$side}.svg"), "{$side}.svg", 'image/svg+xml', null, true);
    }
}
