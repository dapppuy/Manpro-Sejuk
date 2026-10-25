<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment attempt for an order through Midtrans. A failed or expired attempt is kept,
 * and the customer starts a new one.
 *
 * @property int $id
 * @property int $order_id
 * @property PaymentMethod $method
 * @property string|null $gateway_ref Midtrans order_id, unique per attempt.
 * @property string|null $transaction_id
 * @property string|null $va_number
 * @property string|null $biller_code
 * @property string|null $qr_string
 * @property string|null $qr_url
 * @property string|null $deeplink_url
 * @property int $amount
 * @property PaymentStatus $status
 * @property string|null $gateway_status
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $synced_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order $order
 */
#[Fillable([
    'method', 'gateway_ref', 'transaction_id', 'va_number', 'biller_code', 'qr_string', 'qr_url', 'deeplink_url',
    'amount', 'status', 'gateway_status', 'expires_at', 'paid_at', 'synced_at',
])]
class Payment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function isOverdue(): bool
    {
        return $this->status === PaymentStatus::Pending && $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function secondsLeft(): int
    {
        return $this->expires_at === null ? 0 : max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
