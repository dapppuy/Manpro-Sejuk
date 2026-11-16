<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $description
 * @property int|null $price_min
 * @property int|null $price_max
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'name', 'description', 'price_min', 'price_max', 'sort_order'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * Reference price range set by the admin, e.g. "Rp75.000 – Rp150.000".
     */
    public function priceRangeLabel(): ?string
    {
        if ($this->price_min === null) {
            return null;
        }

        return $this->price_max !== null && $this->price_max !== $this->price_min
            ? rupiah($this->price_min).' – '.rupiah($this->price_max)
            : 'Mulai '.rupiah($this->price_min);
    }

    /**
     * Lucide icon name for this service.
     */
    public function icon(): string
    {
        return match ($this->slug) {
            'cuci' => 'droplets',
            'perbaikan' => 'wrench',
            'bongkar' => 'move',
            'berkala' => 'calendar-sync',
            default => 'snowflake',
        };
    }
}
