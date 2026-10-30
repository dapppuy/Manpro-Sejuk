<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A visit to the search page, counted for the search → request conversion KPI.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $service_id
 * @property int $results_count
 * @property CarbonImmutable $created_at
 */
#[Fillable(['user_id', 'service_id', 'results_count'])]
class SearchLog extends Model
{
    public const UPDATED_AT = null;
}
