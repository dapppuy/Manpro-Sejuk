<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\UnitServiceLog;
use App\Notifications\AcUnitNotice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('units:send-service-reminders')]
#[Description('Remind AC owners when a unit is due for its next recommended service')]
class SendUnitServiceReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sent = 0;

        UnitServiceLog::query()
            ->whereNull('reminder_sent_at')
            ->whereDate('next_recommended_service_date', '<=', today())
            ->where(fn (Builder $query) => $query->whereNull('order_id')
                ->orWhereHas('order', fn (Builder $order) => $order->where('status', OrderStatus::Selesai)))
            ->with('unit.user')
            ->each(function (UnitServiceLog $log) use (&$sent) {
                $unit = $log->unit;
                $latest = $unit->completedServiceLogs()->first();

                // A newer visit already replaced this recommendation.
                if ($latest !== null && ! $latest->is($log)) {
                    $log->forceFill(['reminder_sent_at' => now()])->save();

                    return;
                }

                $unit->user->notify(new AcUnitNotice(
                    $unit,
                    'Saatnya servis '.$unit->name,
                    'Teknisi menyarankan '.$unit->name.($unit->room_label ? ' ('.$unit->room_label.')' : '').' diservis lagi mulai '.$log->next_recommended_service_date?->translatedFormat('j F Y').'. Pesan teknisi agar AC tetap dingin dan hemat listrik.',
                ));

                $log->forceFill(['reminder_sent_at' => now()])->save();
                $sent++;
            });

        $this->info("{$sent} pengingat servis terkirim.");

        return self::SUCCESS;
    }
}
