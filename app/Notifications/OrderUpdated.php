<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A short update about an order, shown in the web notification bell and sent by email.
 */
class OrderUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $title,
        public string $body,
        public string $url,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Halo, '.str($notifiable->name)->before(' ').'!')
            ->line($this->body)
            ->action('Buka pesanan', $this->url)
            ->line('Pesanan #'.$this->order->code);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'order_code' => $this->order->code,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
