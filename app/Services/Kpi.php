<?php

namespace App\Services;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\Review;
use App\Models\SearchLog;
use Carbon\CarbonInterface;

/**
 * Product metrics from section 10 of the spec, for orders created in a period.
 */
class Kpi
{
    /**
     * @return array<string, mixed>
     */
    public function summarize(CarbonInterface $from, CarbonInterface $to): array
    {
        $orders = Order::query()->whereBetween('created_at', [$from, $to]);
        $orderIds = (clone $orders)->pluck('id');
        $reachedStatus = fn (OrderStatus $status) => OrderStatusLog::whereIn('order_id', $orderIds)->where('to_status', $status)->distinct()->count('order_id');

        $requests = $orderIds->count();
        $completed = (clone $orders)->where('status', OrderStatus::Selesai)->get(['final_total', 'warranty_fee', 'estimate_min', 'estimate_max', 'rework_count', 'warranty', 'customer_id']);

        $responded = OrderOffer::whereIn('order_id', $orderIds)->whereIn('status', [OfferStatus::Diterima, OfferStatus::Ditolak])->whereNotNull('responded_at')->get(['created_at', 'responded_at']);
        $payments = Payment::whereIn('order_id', $orderIds)->whereIn('status', [PaymentStatus::Paid, PaymentStatus::Failed, PaymentStatus::Expired])->pluck('status');
        $finalCostDecided = OrderStatusLog::whereIn('order_id', $orderIds)->where('from_status', OrderStatus::MenungguPersetujuanBiaya)->where('to_status', '!=', OrderStatus::MenungguPersetujuanBiaya)->get(['order_id', 'to_status'])->unique('order_id');
        $estimateDecided = OrderStatusLog::whereIn('order_id', $orderIds)->where('from_status', OrderStatus::EstimasiMasuk)->whereIn('to_status', [OrderStatus::Terjadwal, OrderStatus::CariCadangan])->get(['to_status']);
        $completedCustomers = Order::where('status', OrderStatus::Selesai)->selectRaw('customer_id, count(*) as total')->groupBy('customer_id')->pluck('total');

        return [
            'funnel' => [
                'Pencarian' => SearchLog::whereBetween('created_at', [$from, $to])->count(),
                'Permintaan' => $requests,
                'Estimasi disetujui' => $reachedStatus(OrderStatus::Terjadwal),
                'Dibayar' => $reachedStatus(OrderStatus::Dikerjakan),
                'Selesai' => $completed->count(),
            ],
            'avg_response_minutes' => $responded->isEmpty() ? null : (int) round($responded->avg(fn (OrderOffer $offer) => $offer->created_at?->diffInMinutes($offer->responded_at) ?? 0)),
            'backup_rate' => $this->percent($reachedStatus(OrderStatus::CariCadangan), $requests),
            'estimate_rejected_rate' => $this->percent($estimateDecided->where('to_status', OrderStatus::CariCadangan)->count(), $estimateDecided->count()),
            'final_cost_rejected_rate' => $this->percent($finalCostDecided->where('to_status', OrderStatus::Dibatalkan)->count(), $finalCostDecided->count()),
            'avg_over_estimate' => $completed->isEmpty() ? null : (int) round($completed->avg(fn (Order $order) => (int) $order->final_total - $order->warranty_fee - (int) $order->estimate_max)),
            'rework_rate' => $this->percent($completed->where('rework_count', '>', 0)->count(), $completed->count()),
            'payment_failed_rate' => $this->percent($payments->filter(fn (PaymentStatus $status) => $status !== PaymentStatus::Paid)->count(), $payments->count()),
            'avg_rating' => ($rating = Review::visible()->whereBetween('created_at', [$from, $to])->avg('rating')) === null ? null : round((float) $rating, 1),
            'repeat_rate' => $this->percent($completedCustomers->filter(fn ($total) => $total > 1)->count(), $completedCustomers->count()),
            'warranty_rate' => $this->percent((clone $orders)->where('warranty', true)->count(), $requests),
        ];
    }

    private function percent(int $part, int $whole): ?int
    {
        return $whole === 0 ? null : (int) round($part / $whole * 100);
    }
}
