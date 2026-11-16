<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A message from the Sejuk team about the account itself, such as a warning or a suspension.
 */
class AccountNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $title, public string $body)
    {
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
            ->line('Balas email ini atau hubungi tim Sejuk jika ada pertanyaan.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => route('pages.help'),
        ];
    }
}
