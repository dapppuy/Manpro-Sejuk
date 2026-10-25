<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $technician_id
 * @property string $area_name
 */
#[Fillable(['area_name'])]
class TechnicianServiceArea extends Model
{
    public $timestamps = false;
}
