<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Models\TechnicianProfile;
use App\Models\User;

/**
 * The selection stages a new technician goes through before they can take orders (PRD F-12).
 * Apart from the document check, the stages happen over WhatsApp with the Sejuk selection team;
 * the admin records each result here and the technician follows the progress on their status page.
 * For an applicant with a certificate, the admin decides per stage whether to run the interview,
 * the practical test, and the reference check, or to skip them.
 */
class TechnicianSelection
{
    /**
     * @var array<string, array{title: string, channel: string, summary: string, steps: list<string>, passes: string, certificate_optional: bool, message: string}>
     */
    public const STAGES = [
        'dokumen' => [
            'title' => 'Pemeriksaan dokumen',
            'channel' => 'Di aplikasi',
            'summary' => 'Tim Sejuk mencocokkan KTP, data usaha, dan bukti kompetensi yang kamu unggah.',
            'steps' => [
                'Nama dan foto KTP cocok dengan data akun.',
                'Sertifikat (BNSP, BLK, atau pelatihan merek) dicek keasliannya, atau cerita pengalaman dan nomor referensi dibaca.',
                'Tidak ada indikasi akun ganda.',
            ],
            'passes' => 'Data lengkap dan sesuai.',
            'certificate_optional' => false,
            'message' => 'Halo :name, saya dari tim seleksi Sejuk. Dokumen pendaftaranmu sudah kami terima dan sedang kami periksa. Kami akan menghubungimu lagi untuk tahap wawancara.',
        ],
        'wawancara' => [
            'title' => 'Wawancara & tes pengetahuan',
            'channel' => 'Video call WhatsApp · ±15 menit',
            'summary' => 'Ngobrol santai sambil menjawab pertanyaan dasar seputar pekerjaan teknisi AC.',
            'steps' => [
                'Perkenalan, pengalaman kerja, dan area yang biasa dilayani.',
                'Keselamatan kerja: bekerja di ketinggian, mematikan listrik sebelum servis.',
                'Kelistrikan dasar: MCB, kabel, grounding, dan cara mengecek arus.',
                'Freon: jenis R22, R32, R410A dan cara membaca tekanan.',
                'Etika layanan: datang tepat waktu dan menjelaskan biaya sebelum mulai bekerja.',
            ],
            'passes' => 'Menjawab dengan benar minimal 4 dari 5 topik.',
            'certificate_optional' => true,
            'message' => 'Halo :name, saya dari tim seleksi Sejuk. Dokumenmu sudah lolos. Kapan kamu bisa video call WhatsApp sekitar 15 menit untuk wawancara dan tes pengetahuan singkat?',
        ],
        'uji_praktik' => [
            'title' => 'Uji praktik',
            'channel' => 'Video call WhatsApp · ±20 menit',
            'summary' => 'Tunjukkan alat kerja dan cara kerjamu secara langsung lewat kamera.',
            'steps' => [
                'Perlihatkan alat kerja: manifold, pompa vakum, jet cleaner, tang ampere.',
                'Peragakan prosedur cuci AC atau cek tekanan freon di unit sungguhan.',
                'Jelaskan satu masalah AC seolah-olah kepada pelanggan.',
            ],
            'passes' => 'Prosedur benar dan tidak ada langkah keselamatan yang terlewat.',
            'certificate_optional' => true,
            'message' => 'Halo :name, kamu lolos wawancara. Tahap berikutnya uji praktik lewat video call WhatsApp: siapkan alat kerja dan satu unit AC untuk diperagakan. Kapan waktu yang cocok?',
        ],
        'referensi' => [
            'title' => 'Cek referensi',
            'channel' => 'WhatsApp ke pelanggan referensi',
            'summary' => 'Tim Sejuk menghubungi nomor referensi yang kamu cantumkan.',
            'steps' => [
                'Menanyakan apakah benar pernah memakai jasamu.',
                'Menanyakan kepuasan dan apakah pernah ada masalah serius.',
            ],
            'passes' => 'Tidak ada laporan masalah serius.',
            'certificate_optional' => true,
            'message' => 'Halo :name, kami akan menghubungi nomor referensi yang kamu cantumkan. Mohon kabari pelangganmu bahwa tim Sejuk akan menghubunginya.',
        ],
        'orientasi' => [
            'title' => 'Orientasi & perjanjian kemitraan',
            'channel' => 'WhatsApp, lalu setujui di aplikasi',
            'summary' => 'Penjelasan cara kerja di Sejuk, lalu kamu menyetujui perjanjian kemitraan di halaman ini.',
            'steps' => [
                'Alur pesanan: kirim estimasi, berangkat, periksa, kirim biaya final, unggah foto sebelum dan sesudah.',
                'Pembayaran hanya lewat Sejuk. Dilarang menerima tunai di luar aplikasi.',
                'Komisi Sejuk :commission% dipotong saat dana diteruskan ke saldomu.',
                'Perbaikan ulang gratis bila pelanggan belum puas, maksimal 2 kali.',
                'Rating rendah atau pelanggaran berulang bisa berujung peringatan atau penangguhan akun.',
            ],
            'passes' => 'Orientasi selesai dan perjanjian disetujui di aplikasi.',
            'certificate_optional' => false,
            'message' => 'Halo :name, selamat, kamu lolos seleksi teknis. Kami ingin menjelaskan cara kerja dan perjanjian kemitraan Sejuk lewat WhatsApp. Setelah itu, setujui perjanjian di halaman status pendaftaranmu.',
        ],
    ];

    public function __construct(private TechnicianProfile $technician) {}

    public static function for(TechnicianProfile $technician): self
    {
        return new self($technician);
    }

    public function usesCertificate(): bool
    {
        return $this->technician->documents->contains(fn ($document) => $document->type === DocumentType::Sertifikat);
    }

    /**
     * Every selection stage, with the commission filled into the orientation steps.
     *
     * @return array<string, array{title: string, channel: string, summary: string, steps: list<string>, passes: string, certificate_optional: bool, message: string}>
     */
    public function stages(): array
    {
        return array_map(fn (array $stage) => [
            ...$stage,
            'steps' => array_map(fn (string $step) => str_replace(':commission', (string) config('sejuk.commission_percent'), $step), $stage['steps']),
        ], self::STAGES);
    }

    /**
     * Whether the admin may skip this stage: only for certified applicants, and only the optional stages.
     */
    public function canSkip(string $stage): bool
    {
        return (self::STAGES[$stage]['certificate_optional'] ?? false) && $this->usesCertificate();
    }

    public function isSkipped(string $stage): bool
    {
        return (bool) ($this->result($stage)['skipped'] ?? false);
    }

    /**
     * @return array{passed_at: string, note: string|null, by: int|null, skipped?: bool}|null
     */
    public function result(string $stage): ?array
    {
        return $this->technician->selection_progress[$stage] ?? null;
    }

    public function isPassed(string $stage): bool
    {
        return $this->result($stage) !== null;
    }

    public function currentStage(): ?string
    {
        foreach (array_keys($this->stages()) as $stage) {
            if (! $this->isPassed($stage)) {
                return $stage;
            }
        }

        return null;
    }

    public function passedCount(): int
    {
        return count(array_filter(array_keys($this->stages()), fn (string $stage) => $this->isPassed($stage)));
    }

    public function isReadyToApprove(): bool
    {
        return $this->currentStage() === null && $this->technician->agreement_accepted_at !== null;
    }

    public function markPassed(string $stage, User $admin, ?string $note): void
    {
        $this->record($stage, ['passed_at' => now()->toIso8601String(), 'note' => $note, 'by' => $admin->id]);
    }

    /**
     * The admin trusts the certificate and leaves this stage out.
     */
    public function skip(string $stage, User $admin, ?string $note): void
    {
        abort_unless($this->canSkip($stage), 422, 'Tahap ini tidak bisa dilewati.');

        $this->record($stage, ['passed_at' => now()->toIso8601String(), 'note' => $note, 'by' => $admin->id, 'skipped' => true]);
    }

    /**
     * @param  array{passed_at: string, note: string|null, by: int|null, skipped?: bool}  $result
     */
    private function record(string $stage, array $result): void
    {
        $this->technician->selection_progress = [
            ...($this->technician->selection_progress ?? []),
            $stage => $result,
        ];
        $this->technician->save();
    }

    public function undo(string $stage): void
    {
        $progress = $this->technician->selection_progress ?? [];
        unset($progress[$stage]);

        $this->technician->selection_progress = $progress;
        $this->technician->save();
    }

    /**
     * Prefilled WhatsApp link from the admin to the technician for a stage.
     */
    public function adminMessageUrl(string $stage): ?string
    {
        $phone = self::whatsappNumber((string) $this->technician->user->phone);

        if ($phone === null) {
            return null;
        }

        $message = str_replace(':name', str($this->technician->user->name)->before(' ')->toString(), self::STAGES[$stage]['message']);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    /**
     * Link for the technician to contact the selection team about their current stage.
     */
    public function technicianMessageUrl(): string
    {
        $stage = $this->currentStage();
        $message = 'Halo tim seleksi Sejuk, saya '.$this->technician->user->name.' ('.$this->technician->business_name.'). '
            .($stage === null ? 'Saya sudah menyelesaikan semua tahap seleksi.' : 'Saya ingin menanyakan tahap '.self::STAGES[$stage]['title'].'.');

        return 'https://wa.me/'.config('sejuk.admin_whatsapp').'?text='.rawurlencode($message);
    }

    /**
     * Normalise an Indonesian phone number (08xx, +628xx, 628xx) into wa.me format.
     */
    public static function whatsappNumber(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}
