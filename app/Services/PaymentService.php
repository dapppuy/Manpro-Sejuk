<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Midtrans\MidtransClient;
use App\Services\Midtrans\MidtransException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * L-10: online payment through the Midtrans Core API. The order status only moves on a
 * signed webhook or a server-side status check, never on a browser redirect.
 */
class PaymentService
{
    public function __construct(private MidtransClient $midtrans, private OrderWorkflow $workflow) {}

    /**
     * Start a payment attempt with the chosen method.
     *
     * @throws InvalidOrderTransition|MidtransException
     */
    public function start(Order $order, PaymentMethod $method): Payment
    {
        if ($order->status !== OrderStatus::MenungguPembayaran || $order->final_total === null) {
            throw new InvalidOrderTransition('Pesanan ini tidak sedang menunggu pembayaran.');
        }

        if ($this->pendingPayment($order) !== null) {
            throw new InvalidOrderTransition('Masih ada pembayaran yang berjalan. Selesaikan atau ganti metode dulu.');
        }

        $minutes = (int) config('sejuk.payment_minutes');
        $payment = $order->payments()->create([
            'method' => $method,
            'amount' => $order->final_total,
            'status' => PaymentStatus::Pending,
            'expires_at' => now()->addMinutes($minutes),
        ]);
        $payment->update(['gateway_ref' => $order->code.'-P'.$payment->id]);

        try {
            $response = $this->midtrans->charge([
                ...$method->chargeParameters($order->code, route('orders.show', $order)),
                'transaction_details' => ['order_id' => $payment->gateway_ref, 'gross_amount' => $payment->amount],
                'item_details' => $order->costItems->map(fn ($item) => [
                    'id' => 'item-'.$item->id,
                    'price' => $item->amount,
                    'quantity' => 1,
                    'name' => mb_substr($item->label, 0, 50),
                ])->values()->all(),
                'customer_details' => [
                    'first_name' => $order->customer->name,
                    'email' => $order->customer->email,
                    'phone' => $order->customer->phone,
                ],
                'custom_expiry' => [
                    'order_time' => now()->format('Y-m-d H:i:s O'),
                    'expiry_duration' => $minutes,
                    'unit' => 'minute',
                ],
            ]);
        } catch (MidtransException $exception) {
            $payment->update(['status' => PaymentStatus::Failed]);

            throw $exception;
        }

        $payment->update([...$this->instructionsFrom($response), 'synced_at' => now()]);

        return $payment;
    }

    /**
     * Handle a Midtrans HTTP notification. Returns false when the signature or the transaction is unknown.
     *
     * @param  array<string, mixed>  $notification
     */
    public function handleNotification(array $notification): bool
    {
        if (! $this->midtrans->hasValidSignature($notification)) {
            Log::warning('Midtrans notification with an invalid signature', Arr::only($notification, ['order_id', 'transaction_status']));

            return false;
        }

        $payment = Payment::where('gateway_ref', $notification['order_id'] ?? null)->first();

        if ($payment === null) {
            return false;
        }

        $this->apply($payment, $notification);

        return true;
    }

    /**
     * Ask Midtrans for the current state of a pending attempt, then expire it locally if its time is up.
     */
    public function sync(Payment $payment): Payment
    {
        if ($payment->status === PaymentStatus::Pending && $payment->gateway_ref !== null && $this->midtrans->isConfigured()) {
            try {
                $this->apply($payment, $this->midtrans->status($payment->gateway_ref));
            } catch (MidtransException $exception) {
                Log::info('Midtrans status check failed', ['payment' => $payment->id, 'error' => $exception->getMessage()]);
            }

            $payment->forceFill(['synced_at' => now()])->save();
        }

        $this->expireIfOverdue($payment);

        return $payment->refresh();
    }

    /**
     * Stop a pending attempt so the customer can choose another method.
     */
    public function cancel(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return;
        }

        $this->cancelAtGateway($payment);
        $payment->update(['status' => PaymentStatus::Cancelled]);
    }

    public function expireIfOverdue(Payment $payment): void
    {
        if (! $payment->isOverdue()) {
            return;
        }

        $this->cancelAtGateway($payment);
        $payment->update(['status' => PaymentStatus::Expired]);
    }

    public function pendingPayment(Order $order): ?Payment
    {
        return $order->payments()->where('status', PaymentStatus::Pending)->latest('id')->first();
    }

    /**
     * Apply a transaction state from Midtrans (webhook or status check) to the attempt and its order.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function apply(Payment $payment, array $transaction): void
    {
        $gatewayStatus = (string) ($transaction['transaction_status'] ?? '');
        $fraudStatus = $transaction['fraud_status'] ?? null;
        $amountMatches = (int) round((float) ($transaction['gross_amount'] ?? 0)) === $payment->amount;

        $newStatus = match (true) {
            in_array($gatewayStatus, ['settlement', 'capture'], true) && in_array($fraudStatus, [null, 'accept'], true) && $amountMatches => PaymentStatus::Paid,
            $gatewayStatus === 'expire' => PaymentStatus::Expired,
            in_array($gatewayStatus, ['deny', 'failure'], true) => PaymentStatus::Failed,
            $gatewayStatus === 'cancel' => PaymentStatus::Cancelled,
            default => null,
        };

        DB::transaction(function () use ($payment, $transaction, $gatewayStatus, $newStatus) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $payment->fill(['gateway_status' => $gatewayStatus, 'transaction_id' => $transaction['transaction_id'] ?? $payment->transaction_id]);

            if ($newStatus === PaymentStatus::Paid && $payment->status !== PaymentStatus::Paid) {
                $payment->fill(['status' => PaymentStatus::Paid, 'paid_at' => $transaction['settlement_time'] ?? now()]);
                $payment->save();
                $this->settleOrder($payment);

                return;
            }

            // A late failure or expiry never overrides a confirmed payment.
            if ($newStatus !== null && $newStatus !== PaymentStatus::Paid && $payment->status === PaymentStatus::Pending) {
                $payment->status = $newStatus;
            }

            $payment->save();
        });
    }

    private function settleOrder(Payment $payment): void
    {
        $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

        if ($order->status === OrderStatus::MenungguPembayaran) {
            $this->workflow->markPaid($order, 'Pembayaran '.$payment->method->label().' diterima, dana ditahan Sejuk');

            return;
        }

        // Money arrived for an order that is no longer waiting (e.g. paid twice). Admin refunds it manually.
        Log::warning('Payment settled for an order that is not awaiting payment', ['order' => $order->code, 'payment' => $payment->id, 'status' => $order->status->value]);
    }

    /**
     * Pull what the customer needs to pay from the charge response.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function instructionsFrom(array $response): array
    {
        $actions = [];

        foreach ((array) ($response['actions'] ?? []) as $action) {
            if (is_array($action) && isset($action['name'], $action['url'])) {
                $actions[(string) $action['name']] = (string) $action['url'];
            }
        }

        return array_filter([
            'transaction_id' => $response['transaction_id'] ?? null,
            'gateway_status' => $response['transaction_status'] ?? null,
            'va_number' => $response['va_numbers'][0]['va_number'] ?? $response['permata_va_number'] ?? $response['bill_key'] ?? null,
            'biller_code' => $response['biller_code'] ?? null,
            'qr_string' => $response['qr_string'] ?? null,
            'qr_url' => $actions['generate-qr-code'] ?? null,
            'deeplink_url' => $actions['deeplink-redirect'] ?? null,
        ], fn ($value) => $value !== null);
    }

    private function cancelAtGateway(Payment $payment): void
    {
        if ($payment->gateway_ref === null || ! $this->midtrans->isConfigured()) {
            return;
        }

        try {
            $this->midtrans->cancel($payment->gateway_ref);
        } catch (MidtransException) {
            // Midtrans also expires the transaction on its side; a late settlement is still honored.
        }
    }
}
