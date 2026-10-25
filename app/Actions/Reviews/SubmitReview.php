<?php

namespace App\Actions\Reviews;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\Review;
use App\Models\TechnicianProfile;
use Illuminate\Support\Facades\DB;

/**
 * L-11: the customer rates a finished order. The technician's rating is updated incrementally
 * so it keeps reviews collected before this order (including imported history).
 */
class SubmitReview
{
    public function handle(Order $order, int $rating, ?string $comment): Review
    {
        if ($order->status !== OrderStatus::Selesai || $order->technician_id === null) {
            throw new InvalidOrderTransition('Ulasan hanya bisa diberikan untuk pesanan yang sudah selesai.');
        }

        if ($order->review()->exists()) {
            throw new InvalidOrderTransition('Kamu sudah memberi ulasan untuk pesanan ini.');
        }

        return DB::transaction(function () use ($order, $rating, $comment) {
            $review = $order->review()->create([
                'customer_id' => $order->customer_id,
                'technician_id' => $order->technician_id,
                'rating' => $rating,
                'comment' => $comment,
            ]);

            $technician = TechnicianProfile::whereKey($order->technician_id)->lockForUpdate()->firstOrFail();
            $count = $technician->reviews_count;

            $technician->update([
                'rating_avg' => round(($technician->rating_avg * $count + $rating) / ($count + 1), 1),
                'reviews_count' => $count + 1,
            ]);

            return $review;
        });
    }
}
