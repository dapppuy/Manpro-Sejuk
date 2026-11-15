<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitServiceLog extends Model
{
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
    ];

    protected $casts = [
        'service_date' => 'date',
        'next_recommended_service_date' => 'date',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CustomerUnit::class, 'customer_unit_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}