<?php

namespace App\Actions\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountNotice;
use Illuminate\Support\Facades\DB;

/**
 * Admin actions on a customer or technician account: warn, suspend, and restore (PRD AD-09 to AD-11).
 */
class ModerateAccount
{
    public function warn(User $user, string $reason, User $admin): void
    {
        $this->ensureModeratable($user);

        DB::transaction(function () use ($user, $reason, $admin) {
            $user->warnings()->create(['admin_id' => $admin->id, 'reason' => $reason]);
            AuditLog::record('user.warned', "Peringatan untuk {$user->name}: {$reason}", $user, actor: $admin);
        });

        $user->notify(new AccountNotice('Peringatan dari tim Sejuk', $reason));
    }

    public function suspend(User $user, string $reason, User $admin): void
    {
        $this->ensureModeratable($user);

        DB::transaction(function () use ($user, $reason, $admin) {
            $user->forceFill(['suspended_at' => now(), 'suspension_reason' => $reason])->save();
            $user->technicianProfile?->update(['is_active' => false, 'is_accepting_orders' => false]);
            AuditLog::record('user.suspended', "{$user->name} ditangguhkan: {$reason}", $user, actor: $admin);
        });

        $user->notify(new AccountNotice('Akunmu ditangguhkan', "Akun Sejuk-mu ditangguhkan sementara. Alasan: {$reason}"));
    }

    public function restore(User $user, User $admin): void
    {
        DB::transaction(function () use ($user, $admin) {
            $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
            $user->technicianProfile?->update(['is_active' => true]);
            AuditLog::record('user.restored', "{$user->name} dipulihkan", $user, actor: $admin);
        });

        $user->notify(new AccountNotice('Akunmu aktif kembali', 'Penangguhan akun Sejuk-mu sudah dicabut. Kamu bisa masuk dan memakai Sejuk seperti biasa.'));
    }

    private function ensureModeratable(User $user): void
    {
        abort_if($user->isAdmin(), 403, 'Akun admin tidak bisa dimoderasi dari sini.');
    }
}
