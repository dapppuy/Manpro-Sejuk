<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A problem reported by whoever uses the AC (e.g. a kos tenant) after scanning its QR sticker.
 *
 * @property int $id
 * @property int $customer_unit_id
 * @property string $reporter_name
 * @property string|null $reporter_contact
 * @property string $issue_description
 * @property string $status
 */
class UnitIssueReport extends Model
{
    public const STATUS_WAITING = 'menunggu_pengelola';

    public const STATUS_ORDERED = 'diproses_order';

    public const STATUS_DONE = 'selesai';

    public const STATUS_DECLINED = 'ditolak';

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_WAITING => 'Menunggu pemilik',
        self::STATUS_ORDERED => 'Teknisi dipesan',
        self::STATUS_DONE => 'Selesai',
        self::STATUS_DECLINED => 'Ditolak',
    ];

    protected $fillable = [
        'customer_unit_id',
        'reported_by_user_id',
        'reporter_name',
        'reporter_contact',
        'issue_description',
        'photo_path',
        'status',
        'order_id',
    ];

    /**
     * @return BelongsTo<CustomerUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(CustomerUnit::class, 'customer_unit_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
