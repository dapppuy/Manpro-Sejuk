<?php

namespace App\Models;

use Database\Factories\UnitServiceLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One service visit recorded on an AC unit's history.
 *
 * @property int $id
 * @property int $customer_unit_id
 * @property int|null $order_id
 * @property int|null $technician_id
 * @property Carbon $service_date
 * @property string $service_type
 * @property string|null $inspection_findings
 * @property string $actions_taken
 * @property string|null $before_photo_path
 * @property string|null $after_photo_path
 * @property Carbon|null $next_recommended_service_date
 * @property Carbon|null $reminder_sent_at
 */
class UnitServiceLog extends Model
{
    /** @use HasFactory<UnitServiceLogFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_unit_id',
        'order_id',
        'technician_id',
        'service_date',
        'service_type',
        'inspection_findings',
        'actions_taken',
        'before_photo_path',
        'after_photo_path',
        'next_recommended_service_date',
        'reminder_sent_at',
    ];

    protected $casts = [
        'service_date' => 'date',
        'next_recommended_service_date' => 'date',
        'reminder_sent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<CustomerUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(CustomerUnit::class, 'customer_unit_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
