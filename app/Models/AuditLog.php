<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Trail of important admin actions, kept for tracing (PRD: log audit).
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $description
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property-read User|null $actor
 */
#[Fillable(['actor_id', 'action', 'subject_type', 'subject_id', 'description', 'properties', 'ip_address'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Readable labels for the action filter.
     *
     * @var array<string, string>
     */
    public const ACTIONS = [
        'technician.approved' => 'Teknisi disetujui',
        'technician.rejected' => 'Teknisi ditolak',
        'technician.stage_passed' => 'Tahap seleksi lolos',
        'technician.stage_skipped' => 'Tahap seleksi dilewati',
        'technician.stage_undone' => 'Tahap seleksi dibatalkan',
        'technician.badge_changed' => 'Badge diubah',
        'technician.active_toggled' => 'Status teknisi diubah',
        'order.cancelled' => 'Pesanan dibatalkan admin',
        'order.dispute_resolved' => 'Sengketa diselesaikan',
        'payout.recorded' => 'Pencairan dicatat',
        'review.visibility_changed' => 'Ulasan disembunyikan/ditampilkan',
        'user.warned' => 'Akun diberi peringatan',
        'user.suspended' => 'Akun ditangguhkan',
        'user.restored' => 'Akun dipulihkan',
        'service.saved' => 'Layanan disimpan',
        'area.saved' => 'Area disimpan',
        'content.saved' => 'Halaman konten disimpan',
    ];

    /**
     * Record an admin action.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $action, string $description, ?Model $subject = null, array $properties = [], ?User $actor = null): self
    {
        return self::create([
            'actor_id' => ($actor ?? auth()->user())?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
