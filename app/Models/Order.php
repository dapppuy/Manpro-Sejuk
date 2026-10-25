<?php

namespace App\Models;

use App\Enums\AcCapacity;
use App\Enums\FundStatus;
use App\Enums\OrderStatus;
use App\Enums\TimeWindow;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int $customer_id
 * @property int|null $technician_id
 * @property int $service_id
 * @property AcCapacity $ac_capacity
 * @property string $ac_brand
 * @property string|null $ac_brand_other
 * @property string $address
 * @property string|null $address_detail
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon $preferred_date
 * @property TimeWindow $time_window
 * @property string|null $notes
 * @property bool $warranty
 * @property int $warranty_fee
 * @property int|null $estimate_min
 * @property int|null $estimate_max
 * @property string|null $estimate_note
 * @property Carbon|null $scheduled_date
 * @property TimeWindow|null $scheduled_window
 * @property int|null $final_total
 * @property int|null $commission_amount
 * @property OrderStatus $status
 * @property FundStatus|null $fund_status
 * @property Carbon|null $response_deadline
 * @property Carbon|null $proof_submitted_at
 * @property int $rework_count
 * @property string|null $cancelled_reason
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $customer
 * @property-read TechnicianProfile|null $technician
 * @property-read Service $service
 */
#[Fillable([
    'customer_id', 'technician_id', 'service_id', 'ac_capacity', 'ac_brand', 'ac_brand_other', 'address',
    'address_detail', 'latitude', 'longitude', 'preferred_date', 'time_window', 'notes', 'warranty', 'warranty_fee',
    'estimate_min', 'estimate_max', 'estimate_note', 'scheduled_date', 'scheduled_window', 'final_total',
    'commission_amount', 'status', 'fund_status', 'response_deadline', 'proof_submitted_at', 'rework_count',
    'cancelled_reason', 'completed_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->code ??= static::generateCode();
        });
    }

    /**
     * Unique order code in the SJK-XXXX format.
     */
    public static function generateCode(): string
    {
        do {
            $code = 'SJK-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ac_capacity' => AcCapacity::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'preferred_date' => 'date',
            'time_window' => TimeWindow::class,
            'warranty' => 'boolean',
            'scheduled_date' => 'date',
            'scheduled_window' => TimeWindow::class,
            'status' => OrderStatus::class,
            'fund_status' => FundStatus::class,
            'response_deadline' => 'datetime',
            'proof_submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', OrderStatus::active());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<TechnicianProfile, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }
}
