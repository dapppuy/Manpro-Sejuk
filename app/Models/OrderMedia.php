<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A photo or video of the broken AC, stored privately and shown only to people involved in the order.
 *
 * @property int $id
 * @property int $order_id
 * @property string $path
 * @property MediaType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['path', 'type'])]
class OrderMedia extends Model
{
    public const DISK = 'local';

    protected $table = 'order_media';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
        ];
    }

    public function url(): string
    {
        return route('orders.media', $this);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
