<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Models\TechnicianProfile;
use Illuminate\Support\Facades\DB;

/**
 * Admin hides a review that breaks the rules, or shows it again. The technician's rating is
 * adjusted the same incremental way SubmitReview adds it.
 */
class ToggleReviewVisibility
{
    public function handle(Review $review): Review
    {
        return DB::transaction(function () use ($review) {
            $technician = TechnicianProfile::whereKey($review->technician_id)->lockForUpdate()->firstOrFail();
            $count = $technician->reviews_count;
            $total = $technician->rating_avg * $count;

            if ($review->is_hidden) {
                $count++;
                $total += $review->rating;
            } else {
                $count = max(0, $count - 1);
                $total = max(0, $total - $review->rating);
            }

            $technician->update([
                'reviews_count' => $count,
                'rating_avg' => $count > 0 ? round($total / $count, 1) : 0,
            ]);

            $review->update(['is_hidden' => ! $review->is_hidden]);

            return $review;
        });
    }
}
