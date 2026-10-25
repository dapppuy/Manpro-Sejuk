<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\OrderVisitReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:send-reminders')]
#[Description('Remind customers and technicians a day and an hour before scheduled visits')]
class SendVisitReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sent = 0;

        Order::query()
            ->where('status', OrderStatus::Terjadwal)
            ->whereBetween('scheduled_date', [today()->toDateString(), today()->addDays(2)->toDateString()])
            ->where(fn ($query) => $query->whereNull('reminded_day_before_at')->orWhereNull('reminded_hour_before_at'))
            ->with(['customer', 'technician.user', 'service'])
            ->each(function (Order $order) use (&$sent) {
                $startsAt = $order->visitStartsAt();

                if ($startsAt === null || $startsAt->isPast()) {
                    return;
                }

                $hoursLeft = now()->diffInMinutes($startsAt) / 60;

                if ($order->reminded_hour_before_at === null && $hoursLeft <= 1) {
                    $this->notify($order, OrderVisitReminder::HOUR_BEFORE);
                    $order->forceFill(['reminded_hour_before_at' => now(), 'reminded_day_before_at' => $order->reminded_day_before_at ?? now()])->save();
                    $sent++;
                } elseif ($order->reminded_day_before_at === null && $hoursLeft <= 24 && $hoursLeft > 1) {
                    $this->notify($order, OrderVisitReminder::DAY_BEFORE);
                    $order->forceFill(['reminded_day_before_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("{$sent} pengingat dikirim.");

        return self::SUCCESS;
    }

    private function notify(Order $order, string $timing): void
    {
        $order->customer->notify(new OrderVisitReminder($order, $timing));
        $order->technician?->user->notify(new OrderVisitReminder($order, $timing));
    }
}
