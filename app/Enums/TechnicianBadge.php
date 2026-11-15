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
            self::Terdaftar => 'Belum Terverifikasi',
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
            self::Terdaftar => 'Identitas belum diperiksa tim Sejuk, belum tampil ke pelanggan',
            self::Identitas => 'KTP dan wajah sudah dicocokkan tim platform',
            self::Sertifikat => 'Identitas terverifikasi dan memiliki sertifikat kompetensi',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Terdaftar => 'clock',
            self::Identitas => 'shield-check',
            self::Sertifikat => 'badge-check',
        };
    }

    /**
     * Whether customers can see technicians holding this badge.
     */
    public function isVerified(): bool
    {
        return $this !== self::Terdaftar;
    }

    /**
     * Badges shown to customers, and the ones an admin can approve a technician with.
     *
     * @return list<self>
     */
    public static function verified(): array
    {
        return array_values(array_filter(self::cases(), fn (self $badge) => $badge->isVerified()));
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
