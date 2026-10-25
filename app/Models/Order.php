<?php

namespace App\Models;

use App\Enums\AcCapacity;
use App\Enums\FundStatus;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TimeWindow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
 * @property CarbonImmutable $preferred_date
 * @property TimeWindow $time_window
 * @property string|null $notes
 * @property bool $warranty
 * @property int $warranty_fee
 * @property int|null $estimate_min
 * @property int|null $estimate_max
 * @property string|null $estimate_note
 * @property CarbonImmutable|null $scheduled_date
 * @property TimeWindow|null $scheduled_window
 * @property int|null $final_total
 * @property int|null $commission_amount
 * @property OrderStatus $status
 * @property FundStatus|null $fund_status
 * @property CarbonImmutable|null $final_cost_submitted_at
 * @property CarbonImmutable|null $reminded_day_before_at
 * @property CarbonImmutable|null $reminded_hour_before_at
 * @property CarbonImmutable|null $response_deadline
 * @property CarbonImmutable|null $proof_submitted_at
 * @property int $rework_count
 * @property string|null $cancelled_reason
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $customer
 * @property-read TechnicianProfile|null $technician
 * @property-read Service $service
 * @property-read Collection<int, OrderOffer> $offers
 * @property-read Collection<int, OrderMedia> $media
 * @property-read Collection<int, OrderCostItem> $costItems
 * @property-read Collection<int, WorkProof> $workProofs
 * @property-read Collection<int, Payment> $payments
 * @property-read Review|null $review
 */
#[Fillable([
    'customer_id', 'technician_id', 'service_id', 'ac_capacity', 'ac_brand', 'ac_brand_other', 'address',
    'address_detail', 'latitude', 'longitude', 'preferred_date', 'time_window', 'notes', 'warranty', 'warranty_fee',
    'estimate_min', 'estimate_max', 'estimate_note', 'scheduled_date', 'scheduled_window', 'final_total',
    'commission_amount', 'final_cost_submitted_at', 'reminded_day_before_at', 'reminded_hour_before_at', 'status', 'fund_status', 'response_deadline', 'proof_submitted_at', 'rework_count',
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
            'final_cost_submitted_at' => 'datetime',
            'reminded_day_before_at' => 'datetime',
            'reminded_hour_before_at' => 'datetime',
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

    /**
     * @return HasMany<OrderOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(OrderOffer::class);
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * @return HasMany<WorkProof, $this>
     */
    public function workProofs(): HasMany
    {
        return $this->hasMany(WorkProof::class)->orderBy('round');
    }

    public function latestProof(): ?WorkProof
    {
        return $this->workProofs->sortByDesc('round')->first();
    }

    /**
     * When the order closes by itself if the customer does not confirm.
     */
    public function autoCompletesAt(): ?CarbonImmutable
    {
        return $this->proof_submitted_at?->addHours((int) config('sejuk.auto_complete_hours'));
    }

    /**
     * What the technician receives once funds are forwarded.
     */
    public function technicianPayout(): int
    {
        return max(0, (int) $this->final_total - (int) $this->commission_amount);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The attempt that settled this order, if any.
     */
    public function paidPayment(): ?Payment
    {
        return $this->payments->firstWhere('status', PaymentStatus::Paid);
    }

    /**
     * @return HasMany<OrderCostItem, $this>
     */
    public function costItems(): HasMany
    {
        return $this->hasMany(OrderCostItem::class)->orderBy('id');
    }

    /**
     * How far the final total lands above the estimate maximum (0 when within the estimate).
     */
    public function amountOverEstimate(): int
    {
        if ($this->final_total === null || $this->estimate_max === null) {
            return 0;
        }

        return max(0, $this->final_total - $this->estimate_max - $this->warranty_fee);
    }

    /**
     * When the technician is due to arrive: the start of the scheduled window.
     */
    public function visitStartsAt(): ?CarbonInterface
    {
        if ($this->scheduled_date === null || $this->scheduled_window === null) {
            return null;
        }

        return $this->scheduled_date->setTime($this->scheduled_window->startHour(), 0);
    }

    /**
     * @return HasMany<OrderMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(OrderMedia::class);
    }

    /**
     * The offer currently waiting on the assigned technician, if any.
     */
    public function pendingOffer(): ?OrderOffer
    {
        return $this->offers
            ->where('technician_id', $this->technician_id)
            ->firstWhere('status', OfferStatus::Menunggu);
    }

    /**
     * Seconds left for the technician to respond, or 0 once the deadline has passed.
     */
    public function secondsToRespond(): int
    {
        if ($this->status !== OrderStatus::MenungguRespons || $this->response_deadline === null) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->response_deadline, false));
    }

    public function brandLabel(): string
    {
        return $this->ac_brand === 'Lainnya' && $this->ac_brand_other ? $this->ac_brand_other : $this->ac_brand;
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->address_detail])->filter()->implode(', ');
    }
}
