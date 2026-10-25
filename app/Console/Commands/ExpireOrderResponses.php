<?php

namespace App\Console\Commands;

use App\Services\OrderWorkflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire-responses')]
#[Description('Move requests whose technician did not respond in time to backup selection')]
class ExpireOrderResponses extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OrderWorkflow $workflow): int
    {
        $expired = $workflow->expireOverdueResponses();

        $this->info("{$expired} permintaan kedaluwarsa.");

        return self::SUCCESS;
    }
}
