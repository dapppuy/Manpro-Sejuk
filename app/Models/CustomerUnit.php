<?php

namespace App\Models;

use App\Enums\AcCapacity;
use App\Enums\OrderStatus;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Database\Factories\CustomerUnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A physical AC unit. Its service history stays with the unit (via its QR sticker), not with whoever lives in the room.
 *
 * @property int $id
 * @property int $user_id
 * @property string $qr_token
 * @property string $name
 * @property string|null $room_label
 * @property bool $is_kos_unit
 * @property string|null $brand
 * @property string|null $capacity_pk
 * @property string|null $ac_type
 * @property string|null $serial_number
 * @property string|null $sticker_photo_path
 * @property Carbon|null $last_serviced_at
 * @property string|null $notes
 */
class CustomerUnit extends Model
{
    /** @use HasFactory<CustomerUnitFactory> */
    use HasFactory;

    /**
     * Sticker and service photos are private: only the owner (and admins) can open them.
     */
    public const PHOTO_DISK = 'local';

    /**
     * @var array<string, string>
     */
    public const TYPES = [
        'split' => 'Split dinding',
        'kaset' => 'Kaset (plafon)',
        'standing' => 'Standing / floor',
        'jendela' => 'Jendela',
        'portable' => 'Portable',
    ];

    protected $fillable = [
        'user_id',
        'qr_token',
        'name',
        'room_label',
        'is_kos_unit',
        'brand',
        'capacity_pk',
        'ac_type',
        'serial_number',
        'sticker_photo_path',
        'last_serviced_at',
        'notes',
    ];

    protected $casts = [
        'is_kos_unit' => 'boolean',
        'last_serviced_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $unit) {
            if (empty($unit->qr_token)) {
                $unit->qr_token = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<UnitServiceLog, $this>
     */
    public function serviceLogs(): HasMany
    {
        return $this->hasMany(UnitServiceLog::class)->orderByDesc('service_date');
    }

    /**
     * Service records that count as history: entered without an order, or from an order that has been completed.
     *
     * @return HasMany<UnitServiceLog, $this>
     */
    public function completedServiceLogs(): HasMany
    {
        return $this->serviceLogs()
            ->where(fn (Builder $query) => $query->whereNull('order_id')
                ->orWhereHas('order', fn (Builder $order) => $order->where('status', OrderStatus::Selesai)))
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<UnitIssueReport, $this>
     */
    public function issueReports(): HasMany
    {
        return $this->hasMany(UnitIssueReport::class)->latest();
    }

    /**
     * @return HasMany<Diagnosis, $this>
     */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class)->latest();
    }

    public function getQrUrlAttribute(): string
    {
        return route('unit.qr.show', ['qrToken' => $this->qr_token]);
    }

    public function typeLabel(): ?string
    {
        return $this->ac_type !== null ? (self::TYPES[$this->ac_type] ?? $this->ac_type) : null;
    }

    /**
     * Short one-line description, e.g. "Daikin · 1 PK · Split dinding".
     */
    public function specLine(): string
    {
        $capacity = AcCapacity::tryFrom((string) $this->capacity_pk);

        return collect([
            $this->brand,
            $capacity !== null && $capacity !== AcCapacity::Unknown ? $capacity->label() : $this->capacity_pk,
            $this->typeLabel(),
        ])->filter()->implode(' · ');
    }

    /**
     * The QR code for the sticker, as inline SVG pointing at the public history page.
     */
    public function qrSvg(int $size = 220): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($this->qr_url);
    }
}
