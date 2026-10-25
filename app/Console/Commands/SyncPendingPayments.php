<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:sync')]
#[Description('Reconcile pending payments with Midtrans and expire the ones past their deadline')]
class SyncPendingPayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PaymentService $payments): int
    {
        $pending = Payment::where('status', PaymentStatus::Pending)->get();

        $pending->each(fn (Payment $payment) => $payments->sync($payment));

        $this->info("{$pending->count()} pembayaran dicek.");

        return self::SUCCESS;
    }
}
