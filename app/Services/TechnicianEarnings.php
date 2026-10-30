<?php

namespace App\Services;

use App\Enums\FundStatus;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TechnicianProfile;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Money and workload figures for a technician's dashboard and earnings page.
 */
class TechnicianEarnings
{
    /**
     * Net earnings (after commission) from orders completed in the given month.
     */
    public function earnedIn(TechnicianProfile $technician, CarbonInterface $month): int
    {
        return (int) $technician->orders()
            ->where('status', OrderStatus::Selesai)
            ->whereBetween('completed_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->get(['final_total', 'commission_amount'])
            ->sum(fn (Order $order) => $order->technicianPayout());
    }

    /**
     * @return array{this_month: int, last_month: int, change_percent: int|null}
     */
    public function monthlyComparison(TechnicianProfile $technician): array
    {
        $thisMonth = $this->earnedIn($technician, now());
        $lastMonth = $this->earnedIn($technician, now()->subMonthNoOverflow());

        return [
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'change_percent' => $lastMonth > 0 ? (int) round(($thisMonth - $lastMonth) / $lastMonth * 100) : null,
        ];
    }

    /**
     * @return array{active: int, needs_action: int}
     */
    public function workload(TechnicianProfile $technician): array
    {
        $statuses = $technician->orders()->whereIn('status', OrderStatus::active())->pluck('status');
        $needsAction = [OrderStatus::MenungguRespons, OrderStatus::Terjadwal, OrderStatus::Dikerjakan, OrderStatus::PerbaikanUlang];

        return [
            'active' => $statuses->filter(fn (OrderStatus $status) => $status !== OrderStatus::CariCadangan)->count(),
            'needs_action' => $statuses->filter(fn (OrderStatus $status) => in_array($status, $needsAction, true))->count(),
        ];
    }

    /**
     * Share of accepted jobs that ended completed, out of those that ended at all.
     *
     * @return array{rate: int|null, completed: int, finished: int}
     */
    public function completionRate(TechnicianProfile $technician): array
    {
        $statuses = $technician->orders()
            ->whereHas('offers', fn ($offers) => $offers->where('technician_id', $technician->id)->where('status', OfferStatus::Diterima))
            ->whereIn('status', [OrderStatus::Selesai, OrderStatus::Dibatalkan, OrderStatus::Sengketa])
            ->pluck('status');

        $completed = $statuses->filter(fn (OrderStatus $status) => $status === OrderStatus::Selesai)->count();

        return [
            'rate' => $statuses->isEmpty() ? null : (int) round($completed / $statuses->count() * 100),
            'completed' => $completed,
            'finished' => $statuses->count(),
        ];
    }

    /**
     * Totals by fund state: held by Sejuk, forwarded but not yet transferred, and already paid out.
     *
     * @return array{held: int, forwarded: int, paid_out: int}
     */
    public function balances(TechnicianProfile $technician): array
    {
        $orders = $technician->orders()
            ->whereIn('fund_status', [FundStatus::Ditahan, FundStatus::Diteruskan, FundStatus::Dicairkan])
            ->get(['fund_status', 'final_total', 'commission_amount']);

        $sum = fn (FundStatus $status) => (int) $orders->where('fund_status', $status)->sum(fn (Order $order) => $order->technicianPayout());

        return [
            'held' => $sum(FundStatus::Ditahan),
            'forwarded' => $sum(FundStatus::Diteruskan),
            'paid_out' => $sum(FundStatus::Dicairkan),
        ];
    }

    /**
     * Orders with money attached, newest first.
     *
     * @return Collection<int, Order>
     */
    public function ledger(TechnicianProfile $technician, int $limit = 50): Collection
    {
        return $technician->orders()
            ->whereNotNull('fund_status')
            ->where('fund_status', '!=', FundStatus::Dikembalikan)
            ->with(['service', 'customer', 'payout'])
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }
}
