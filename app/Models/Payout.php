<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bank transfer from Sejuk to a technician covering one or more forwarded orders.
 *
 * @property int $id
 * @property int $technician_id
 * @property int $amount
 * @property string|null $bank_name
 * @property string|null $account_name
 * @property string|null $account_number
 * @property string $status
 * @property CarbonImmutable|null $transferred_at
 * @property string|null $reference
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TechnicianProfile $technician
 * @property-read Collection<int, Order> $orders
 */
#[Fillable(['amount', 'bank_name', 'account_name', 'account_number', 'status', 'transferred_at', 'reference', 'created_by'])]
class Payout extends Model
{
    public const STATUS_TRANSFERRED = 'ditransfer';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'transferred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TechnicianProfile, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
