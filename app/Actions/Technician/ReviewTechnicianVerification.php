<?php

namespace App\Actions\Technician;

use App\Enums\DocumentStatus;
use App\Enums\TechnicianBadge;
use App\Enums\TimeWindow;
use App\Enums\VerificationStatus;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * An admin's decision on a pending technician: approve with a badge, or reject with a reason.
 */
class ReviewTechnicianVerification
{
    /**
     * Weekly windows given to newly approved technicians until they set their own schedule.
     *
     * @var list<int>
     */
    private const DEFAULT_WORK_DAYS = [1, 2, 3, 4, 5, 6];

    public function approve(TechnicianProfile $technician, TechnicianBadge $badge, User $admin): void
    {
        DB::transaction(function () use ($technician, $badge, $admin) {
            $technician->update([
                'verification_status' => VerificationStatus::Approved,
                'verification_note' => null,
                'verified_at' => now(),
                'badge' => $badge,
                'is_active' => true,
                'is_accepting_orders' => true,
            ]);

            $technician->documents()->update([
                'status' => DocumentStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if (! $technician->availabilities()->exists()) {
                foreach (self::DEFAULT_WORK_DAYS as $day) {
                    foreach ([TimeWindow::Pagi, TimeWindow::Siang, TimeWindow::Sore] as $window) {
                        $technician->availabilities()->create(['day_of_week' => $day, 'time_window' => $window, 'is_available' => true]);
                    }
                }
            }
        });
    }

    public function reject(TechnicianProfile $technician, string $reason, User $admin): void
    {
        DB::transaction(function () use ($technician, $reason, $admin) {
            $technician->update([
                'verification_status' => VerificationStatus::Rejected,
                'verification_note' => $reason,
                'is_accepting_orders' => false,
            ]);

            $technician->documents()->update([
                'status' => DocumentStatus::Rejected,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
        });
    }
}
