<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Before/after photos the technician uploads when marking the job done. Each rework adds a new round.
 *
 * @property int $id
 * @property int $order_id
 * @property int $round
 * @property string $before_path
 * @property string $after_path
 * @property string|null $note
 * @property CarbonImmutable|null $rejected_at
 * @property string|null $complaint What the customer said was still wrong.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order $order
 */
#[Fillable(['round', 'before_path', 'after_path', 'note', 'rejected_at', 'complaint'])]
class WorkProof extends Model
{
    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'rejected_at' => 'datetime',
        ];
    }

    public function photoUrl(string $side): string
    {
        return route('orders.proofs.photo', ['proof' => $this, 'side' => $side]);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
