<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Identity and competency files. Stored on the private disk and never shown to customers.
 *
 * @property int $id
 * @property int $technician_id
 * @property DocumentType $type
 * @property string $path
 * @property DocumentStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'path', 'status', 'reviewed_by', 'reviewed_at'])]
class TechnicianDocument extends Model
{
    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPdf(): bool
    {
        return Str::endsWith(Str::lower($this->path), '.pdf');
    }

    /**
     * @return BelongsTo<TechnicianProfile, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_id');
    }
}
