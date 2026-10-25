<?php

namespace App\Enums;

enum OrderStatus: string
{
    case MenungguRespons = 'menunggu_respons';
    case CariCadangan = 'cari_cadangan';
    case EstimasiMasuk = 'estimasi_masuk';
    case Terjadwal = 'terjadwal';
    case MenungguPersetujuanBiaya = 'menunggu_persetujuan_biaya';
    case MenungguPembayaran = 'menunggu_pembayaran';
    case Dikerjakan = 'dikerjakan';
    case MenungguKonfirmasi = 'menunggu_konfirmasi';
    case PerbaikanUlang = 'perbaikan_ulang';
    case Sengketa = 'sengketa';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::MenungguRespons => 'Menunggu respons',
            self::CariCadangan => 'Mencari cadangan',
            self::EstimasiMasuk => 'Estimasi masuk',
            self::Terjadwal => 'Terjadwal',
            self::MenungguPersetujuanBiaya => 'Perlu persetujuan',
            self::MenungguPembayaran => 'Menunggu pembayaran',
            self::Dikerjakan => 'Sedang dikerjakan',
            self::MenungguKonfirmasi => 'Menunggu konfirmasimu',
            self::PerbaikanUlang => 'Perbaikan ulang',
            self::Sengketa => 'Ditinjau admin',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /**
     * Tone used by status pills: ice, sun, leaf, coral, or mute.
     */
    public function tone(): string
    {
        return match ($this) {
            self::MenungguRespons, self::MenungguPersetujuanBiaya, self::MenungguPembayaran, self::MenungguKonfirmasi, self::PerbaikanUlang => 'sun',
            self::CariCadangan, self::EstimasiMasuk, self::Terjadwal, self::Dikerjakan => 'ice',
            self::Sengketa, self::Dibatalkan => 'coral',
            self::Selesai => 'leaf',
        };
    }

    /**
     * Index (0-7) in the 8-step customer progress bar:
     * Respons, Estimasi, Terjadwal, Biaya final, Pembayaran, Pengerjaan, Konfirmasi, Selesai.
     */
    public function progressStep(): int
    {
        return match ($this) {
            self::MenungguRespons, self::CariCadangan, self::Dibatalkan => 0,
            self::EstimasiMasuk => 1,
            self::Terjadwal => 2,
            self::MenungguPersetujuanBiaya => 3,
            self::MenungguPembayaran => 4,
            self::Dikerjakan, self::PerbaikanUlang => 5,
            self::MenungguKonfirmasi, self::Sengketa => 6,
            self::Selesai => 7,
        };
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Selesai, self::Dibatalkan], true);
    }

    public function isCancellableByCustomer(): bool
    {
        return in_array($this, [self::MenungguRespons, self::CariCadangan, self::EstimasiMasuk, self::Terjadwal], true);
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isActive()));
    }
}
