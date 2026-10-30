<?php

namespace App\Models;

use App\Enums\TechnicianBadge;
use App\Enums\TimeWindow;
use App\Enums\VerificationStatus;
use Carbon\CarbonInterface;
use Database\Factories\TechnicianProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $business_name
 * @property string $slug
 * @property string|null $bio
 * @property int $years_experience
 * @property int $jobs_count
 * @property int $service_radius_km
 * @property string|null $base_area
 * @property float|null $latitude
 * @property float|null $longitude
 * @property TechnicianBadge $badge
 * @property VerificationStatus $verification_status
 * @property string|null $verification_note
 * @property Carbon|null $verified_at
 * @property bool $is_active
 * @property bool $is_accepting_orders
 * @property bool $is_promoted
 * @property Carbon|null $promoted_until
 * @property int|null $avg_response_minutes
 * @property float $rating_avg
 * @property int $reviews_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Service> $services
 * @property-read Collection<int, TechnicianPrice> $prices
 * @property-read Collection<int, TechnicianServiceArea> $serviceAreas
 * @property-read Collection<int, TechnicianBrand> $brands
 * @property-read Collection<int, TechnicianPhoto> $photos
 * @property-read Collection<int, TechnicianAvailability> $availabilities
 * @property-read Collection<int, Review> $reviews
 * @property-read Collection<int, TechnicianDocument> $documents
 * @property-read TechnicianExperience|null $experience
 * @property-read TechnicianPayoutAccount|null $payoutAccount
 */
#[Fillable([
    'business_name', 'slug', 'bio', 'years_experience', 'jobs_count', 'service_radius_km', 'base_area',
    'latitude', 'longitude', 'badge', 'verification_status', 'verification_note', 'verified_at', 'is_active',
    'is_accepting_orders', 'is_promoted', 'promoted_until', 'avg_response_minutes', 'rating_avg', 'reviews_count',
])]
class TechnicianProfile extends Model
{
    /** @use HasFactory<TechnicianProfileFactory> */
    use HasFactory;

    /**
     * Distance from the searching customer, set by the search service.
     */
    public ?float $distanceKm = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'badge' => TechnicianBadge::class,
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_accepting_orders' => 'boolean',
            'is_promoted' => 'boolean',
            'promoted_until' => 'datetime',
            'rating_avg' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Technicians customers can find and book.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('verification_status', VerificationStatus::Approved)
            ->where('is_active', true);
    }

    public function isPromotedNow(): bool
    {
        return $this->is_promoted && ($this->promoted_until === null || $this->promoted_until->isFuture());
    }

    /**
     * Available time windows on the given date, from weekly rules with date overrides.
     *
     * Today's windows close two hours after they start.
     *
     * @return list<TimeWindow>
     */
    public function windowsOn(CarbonInterface $date): array
    {
        $rules = $this->availabilities;
        $overrides = $rules->filter(fn (TechnicianAvailability $rule) => $rule->date !== null && $rule->date->isSameDay($date));
        $weekly = $rules->filter(fn (TechnicianAvailability $rule) => $rule->date === null && $rule->day_of_week === $date->dayOfWeek);

        return array_values(array_filter(TimeWindow::cases(), function (TimeWindow $window) use ($overrides, $weekly, $date) {
            $override = $overrides->first(fn (TechnicianAvailability $rule) => $rule->time_window === $window);
            $available = $override !== null
                ? $override->is_available
                : $weekly->contains(fn (TechnicianAvailability $rule) => $rule->time_window === $window && $rule->is_available);

            return $available && (! $date->isToday() || now()->hour < $window->startHour() + 2);
        }));
    }

    public function isAvailableToday(): bool
    {
        return $this->is_accepting_orders && $this->windowsOn(now()->startOfDay()) !== [];
    }

    /**
     * The next date and window this technician can visit, within the booking horizon.
     *
     * @return array{date: CarbonInterface, window: TimeWindow}|null
     */
    public function nextSlot(): ?array
    {
        if (! $this->is_accepting_orders) {
            return null;
        }

        foreach (range(0, (int) config('sejuk.booking_days_ahead')) as $offset) {
            $date = now()->startOfDay()->addDays($offset);
            $window = $this->windowsOn($date)[0] ?? null;

            if ($window !== null) {
                return ['date' => $date, 'window' => $window];
            }
        }

        return null;
    }

    /**
     * Lowest minimum price for a service ("Mulai dari").
     */
    public function startingPriceFor(int $serviceId): ?int
    {
        $minimum = $this->prices->where('service_id', $serviceId)->min('price_min');

        return $minimum === null ? null : (int) $minimum;
    }

    public function startingPrice(): ?int
    {
        $minimum = $this->prices->min('price_min');

        return $minimum === null ? null : (int) $minimum;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'technician_services', 'technician_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<TechnicianPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(TechnicianPrice::class, 'technician_id');
    }

    /**
     * @return HasMany<TechnicianServiceArea, $this>
     */
    public function serviceAreas(): HasMany
    {
        return $this->hasMany(TechnicianServiceArea::class, 'technician_id');
    }

    /**
     * @return HasMany<TechnicianBrand, $this>
     */
    public function brands(): HasMany
    {
        return $this->hasMany(TechnicianBrand::class, 'technician_id');
    }

    /**
     * @return HasMany<TechnicianPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(TechnicianPhoto::class, 'technician_id');
    }

    /**
     * @return HasMany<TechnicianAvailability, $this>
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(TechnicianAvailability::class, 'technician_id');
    }

    /**
     * @return HasOne<TechnicianPayoutAccount, $this>
     */
    public function payoutAccount(): HasOne
    {
        return $this->hasOne(TechnicianPayoutAccount::class, 'technician_id');
    }

    /**
     * @return HasMany<Payout, $this>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'technician_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'technician_id');
    }

    /**
     * @return HasMany<OrderOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(OrderOffer::class, 'technician_id');
    }

    /**
     * Suggested estimate range for an order, from this technician's reference prices.
     * Larger units (1,5 PK and up) use the second price row when one exists.
     *
     * @return array{min: int, max: int}|null
     */
    public function suggestedEstimate(Order $order): ?array
    {
        $rows = $this->prices->where('service_id', $order->service_id)->sortBy('price_min')->values();

        if ($rows->isEmpty()) {
            return null;
        }

        $isLarge = in_array($order->ac_capacity->value, ['1.5', '2'], true);
        $row = $isLarge && $rows->count() > 1 ? $rows[1] : $rows[0];

        return ['min' => $row->price_min, 'max' => $row->price_max];
    }

    /**
     * @return HasMany<TechnicianDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(TechnicianDocument::class, 'technician_id');
    }

    /**
     * @return HasOne<TechnicianExperience, $this>
     */
    public function experience(): HasOne
    {
        return $this->hasOne(TechnicianExperience::class, 'technician_id');
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'technician_id');
    }
}
