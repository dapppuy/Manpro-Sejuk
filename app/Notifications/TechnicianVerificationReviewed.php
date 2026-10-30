<?php

namespace App\Notifications;

use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a technician whether their registration was approved or rejected.
 */
class TechnicianVerificationReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TechnicianProfile $technician, public bool $approved)
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
        $mail = (new MailMessage)->subject($this->title())->greeting('Halo, '.str($notifiable->name)->before(' ').'!')->line($this->body());

        return $this->approved
            ? $mail->action('Buka Sejuk Mitra', route('mitra.dashboard'))
            : $mail->action('Lihat status verifikasi', route('mitra.verification'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->approved ? route('mitra.dashboard') : route('mitra.verification'),
        ];
    }

    private function title(): string
    {
        return $this->approved ? 'Akun Sejuk Mitra sudah aktif' : 'Verifikasi akun belum disetujui';
    }

    private function body(): string
    {
        return $this->approved
            ? "Profil {$this->technician->business_name} sudah tampil di pencarian dengan badge {$this->technician->badge->label()}. Kamu siap menerima pesanan."
            : 'Alasan: '.($this->technician->verification_note ?: 'data belum bisa diverifikasi.');
    }
}
