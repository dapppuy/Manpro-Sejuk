<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomerUnit extends Model
{
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceLogs(): HasMany
    {
        return $this->hasMany(UnitServiceLog::class)->orderByDesc('service_date');
    }

    public function issueReports(): HasMany
    {
        return $this->hasMany(UnitIssueReport::class)->latest();
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class)->latest();
    }

    public function getQrUrlAttribute(): string
    {
        return route('unit.qr.show', ['qrToken' => $this->qr_token]);
    }
}