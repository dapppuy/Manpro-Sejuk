<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $technician_id
 * @property string $brand
 */
#[Fillable(['brand'])]
class TechnicianBrand extends Model
{
    public $timestamps = false;
}
