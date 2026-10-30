<?php

namespace App\Events;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by OrderWorkflow after every status transition. The actor is null for system changes
 * (deadlines, payment webhooks, auto-complete).
 */
class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public Order $order,
        public ?OrderStatus $from,
        public OrderStatus $to,
        public ?User $actor,
    ) {}
}
