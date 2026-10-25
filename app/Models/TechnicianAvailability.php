<?php

namespace App\Models;

use App\Enums\TimeWindow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A weekly rule (day_of_week set) or a one-off date override (date set).
 *
 * @property int $id
 * @property int $technician_id
 * @property int|null $day_of_week
 * @property Carbon|null $date
 * @property TimeWindow $time_window
 * @property bool $is_available
 */
#[Fillable(['day_of_week', 'date', 'time_window', 'is_available'])]
class TechnicianAvailability extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'date' => 'date',
            'time_window' => TimeWindow::class,
            'is_available' => 'boolean',
        ];
    }
}
