<?php

namespace App\Console\Commands;

use App\Services\OrderWorkflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:auto-complete')]
#[Description('Complete orders the customer did not confirm within 24 hours of the work proof and forward the funds')]
class AutoCompleteOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OrderWorkflow $workflow): int
    {
        $completed = $workflow->autoCompleteOverdue();

        $this->info("{$completed} pesanan diselesaikan otomatis.");

        return self::SUCCESS;
    }
}
