<?php

namespace App\Enums;

enum TechnicianBadge: string
{
    case Terdaftar = 'terdaftar';
    case Identitas = 'identitas';
    case Sertifikat = 'sertifikat';

    public function label(): string
    {
        return match ($this) {
            self::Terdaftar => 'Terdaftar',
            self::Identitas => 'Identitas Terverifikasi',
            self::Sertifikat => 'Bersertifikat',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Terdaftar => 1,
            self::Identitas => 2,
            self::Sertifikat => 3,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Terdaftar => 'Akun teknisi aktif di platform',
            self::Identitas => 'KTP dan wajah sudah dicocokkan tim platform',
            self::Sertifikat => 'Identitas terverifikasi dan memiliki sertifikat kompetensi',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Terdaftar => 'user-round',
            self::Identitas => 'shield-check',
            self::Sertifikat => 'badge-check',
        };
    }

    /**
     * Badges at or above the given rank.
     *
     * @return list<self>
     */
    public static function atLeast(self $minimum): array
    {
        return array_values(array_filter(self::cases(), fn (self $badge) => $badge->rank() >= $minimum->rank()));
    }
}
