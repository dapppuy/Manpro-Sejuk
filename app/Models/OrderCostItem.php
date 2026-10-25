<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One line of the final cost the technician sends after inspecting the AC.
 * Additional items are work found on site beyond the original request and are highlighted to the customer.
 *
 * @property int $id
 * @property int $order_id
 * @property string $label
 * @property int $amount
 * @property bool $is_additional
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['label', 'amount', 'is_additional'])]
class OrderCostItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_additional' => 'boolean',
        ];
    }
}
