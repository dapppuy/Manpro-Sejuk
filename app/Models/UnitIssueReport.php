<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitIssueReport extends Model
{
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CustomerUnit::class, 'customer_unit_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}