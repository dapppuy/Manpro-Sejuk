<?php

namespace App\Notifications;

use App\Models\TechnicianProfile;
use App\Models\User;
use App\Support\TechnicianSelection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an applicant that they passed a selection stage and what comes next.
 */
class TechnicianSelectionUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TechnicianProfile $technician, public string $passedStage)
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
            ->subject($this->title())
            ->greeting('Halo, '.str($notifiable->name)->before(' ').'!')
            ->line($this->body())
            ->action('Lihat tahapan seleksi', route('mitra.verification'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => route('mitra.verification'),
        ];
    }

    private function title(): string
    {
        $stage = TechnicianSelection::STAGES[$this->passedStage]['title'];

        return TechnicianSelection::for($this->technician)->isSkipped($this->passedStage)
            ? "Tahap {$stage} tidak diperlukan"
            : "Lolos tahap {$stage}";
    }

    private function body(): string
    {
        $selection = TechnicianSelection::for($this->technician->loadMissing('documents'));
        $next = $selection->currentStage();

        if ($next === null) {
            return $this->technician->agreement_accepted_at === null
                ? 'Semua tahap seleksi selesai. Setujui perjanjian kemitraan di halaman status pendaftaranmu agar akun bisa diaktifkan.'
                : 'Semua tahap seleksi selesai. Tim Sejuk segera mengaktifkan akunmu.';
        }

        return 'Tahap berikutnya: '.TechnicianSelection::STAGES[$next]['title'].' ('.TechnicianSelection::STAGES[$next]['channel'].'). Tim Sejuk akan menghubungimu lewat WhatsApp.';
    }
}
