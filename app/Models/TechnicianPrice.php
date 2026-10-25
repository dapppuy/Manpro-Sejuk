<?php

namespace App\Models;

use Database\Factories\TechnicianPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $technician_id
 * @property int $service_id
 * @property string $note
 * @property int $price_min
 * @property int $price_max
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Service $service
 */
#[Fillable(['service_id', 'note', 'price_min', 'price_max'])]
class TechnicianPrice extends Model
{
    /** @use HasFactory<TechnicianPriceFactory> */
    use HasFactory;

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
}
