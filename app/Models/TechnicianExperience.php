<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Competency proof for technicians without a certificate.
 *
 * @property int $id
 * @property int $technician_id
 * @property int $years
 * @property string $reference_phone
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['years', 'reference_phone', 'description'])]
class TechnicianExperience extends Model {}
