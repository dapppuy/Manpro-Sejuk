<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * The customer who placed it, any technician it was offered to, and admins.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin() || $order->customer_id === $user->id) {
            return true;
        }

        $technicianId = $user->technicianProfile?->id;

        return $technicianId !== null && $order->offers()->where('technician_id', $technicianId)->exists();
    }

    /**
     * Customer actions: approve or reject an estimate, pick a backup, cancel.
     */
    public function manage(User $user, Order $order): bool
    {
        return $order->customer_id === $user->id;
    }

    /**
     * Technician actions on the order currently assigned to them.
     */
    public function respond(User $user, Order $order): bool
    {
        $technicianId = $user->technicianProfile?->id;

        return $technicianId !== null && $order->technician_id === $technicianId;
    }
}
