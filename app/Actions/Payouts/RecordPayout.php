<?php

namespace App\Actions\Payouts;

use App\Enums\FundStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\Payout;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin confirms a bank transfer to a technician covering every forwarded order not yet paid out.
 */
class RecordPayout
{
    public function handle(TechnicianProfile $technician, User $admin, ?string $reference): Payout
    {
        $account = $technician->payoutAccount;

        if ($account === null) {
            throw new InvalidOrderTransition('Teknisi ini belum mengisi rekening pencairan.');
        }

        return DB::transaction(function () use ($technician, $admin, $reference, $account) {
            $orders = $technician->orders()
                ->where('fund_status', FundStatus::Diteruskan)
                ->whereNull('payout_id')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                throw new InvalidOrderTransition('Tidak ada saldo yang perlu dicairkan.');
            }

            $payout = $technician->payouts()->create([
                'amount' => (int) $orders->sum(fn (Order $order) => $order->technicianPayout()),
                'bank_name' => $account->bank_name,
                'account_name' => $account->account_name,
                'account_number' => $account->account_number,
                'status' => Payout::STATUS_TRANSFERRED,
                'transferred_at' => now(),
                'reference' => $reference,
                'created_by' => $admin->id,
            ]);

            Order::whereKey($orders->modelKeys())->update(['fund_status' => FundStatus::Dicairkan, 'payout_id' => $payout->id]);

            return $payout;
        });
    }
}
