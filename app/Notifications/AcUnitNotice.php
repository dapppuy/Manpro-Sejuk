<?php

namespace App\Notifications;

use App\Models\CustomerUnit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * News about one of the owner's AC units: a periodic service reminder or a tenant's problem report.
 */
class AcUnitNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CustomerUnit $unit,
        public string $title,
        public string $body,
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
            ->action('Buka riwayat AC', route('units.show', $this->unit));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'unit_id' => $this->unit->id,
            'title' => $this->title,
            'body' => $this->body,
            'url' => route('units.show', $this->unit),
        ];
    }
}
