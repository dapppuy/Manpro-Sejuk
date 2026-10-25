<?php

namespace App\Models;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One technician an order was offered to. Keeps the history when an order moves between technicians.
 *
 * @property int $id
 * @property int $order_id
 * @property int $technician_id
 * @property OfferStatus $status
 * @property Carbon|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read TechnicianProfile $technician
 */
#[Fillable(['technician_id', 'status', 'responded_at'])]
class OrderOffer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<TechnicianProfile, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_id');
    }
}
