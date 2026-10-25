<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminds the customer and the technician of a scheduled visit, a day before and an hour before.
 */
class OrderVisitReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public const DAY_BEFORE = 'day';

    public const HOUR_BEFORE = 'hour';

    public function __construct(public Order $order, public string $timing) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title($notifiable))
            ->greeting('Halo, '.str($notifiable->name)->before(' ').'!')
            ->line($this->body($notifiable))
            ->line('Lokasi: '.$this->order->fullAddress())
            ->action('Lihat pesanan', $this->url($notifiable))
            ->line('Pesanan #'.$this->order->code);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'order_code' => $this->order->code,
            'timing' => $this->timing,
            'title' => $this->title($notifiable),
            'body' => $this->body($notifiable),
            'url' => $this->url($notifiable),
        ];
    }

    private function title(User $notifiable): string
    {
        $when = $this->timing === self::DAY_BEFORE ? 'besok' : '1 jam lagi';

        return $notifiable->isTechnician()
            ? "Pengingat: kunjungan {$when} ke {$this->order->customer->name}"
            : "Pengingat: teknisi datang {$when}";
    }

    private function body(User $notifiable): string
    {
        $slot = $this->order->scheduled_date?->translatedFormat('l, j F').', '.$this->order->scheduled_window?->range().' WIB';

        return $notifiable->isTechnician()
            ? "Jadwal kunjungan {$this->order->service->name} untuk {$this->order->customer->name}: {$slot}."
            : "{$this->order->technician?->business_name} akan datang {$slot} untuk {$this->order->service->name}.";
    }

    private function url(User $notifiable): string
    {
        return $notifiable->isTechnician()
            ? route('mitra.orders.show', $this->order)
            : route('orders.show', $this->order);
    }
}
