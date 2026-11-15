<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Diagnosis extends Model
{
    /**
     * Complaint photos are private: only the customer (and later their technician) may see them.
     */
    public const PHOTO_DISK = 'local';

    protected $fillable = [
        'user_id',
        'customer_unit_id',
        'complaint_text',
        'sticker_photo_path',
        'condition_photo_path',
        'detected_unit_info',
        'possible_causes',
        'confidence_level',
        'confidence_score',
        'urgency_level',
        'recommended_service_slug',
        'preliminary_advice',
        'raw_ai_response',
        'actual_cause_by_technician',
        'is_accurate',
    ];

    protected $casts = [
        'detected_unit_info' => 'array',
        'possible_causes' => 'array',
        'raw_ai_response' => 'array',
        'confidence_score' => 'integer',
        'is_accurate' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CustomerUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(CustomerUnit::class, 'customer_unit_id');
    }

    /**
     * @return HasOne<Order, $this>
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }
}
