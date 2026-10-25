<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $technician_id
 * @property string $path
 * @property string|null $caption
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['path', 'caption'])]
class TechnicianPhoto extends Model
{
    public function url(): string
    {
        return Str::startsWith($this->path, ['http://', 'https://', '/'])
            ? $this->path
            : Storage::disk('public')->url($this->path);
    }
}
