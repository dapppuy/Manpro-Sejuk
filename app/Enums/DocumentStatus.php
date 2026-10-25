<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Sedang diperiksa',
            self::Approved => 'Terverifikasi',
            self::Rejected => 'Ditolak',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'text-sun-ink',
            self::Approved => 'text-leaf',
            self::Rejected => 'text-coral',
        };
    }
}
