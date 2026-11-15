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
