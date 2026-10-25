<?php

namespace App\Enums;

enum DocumentType: string
{
    case Ktp = 'ktp';
    case Sertifikat = 'sertifikat';

    public function label(): string
    {
        return match ($this) {
            self::Ktp => 'KTP',
            self::Sertifikat => 'Sertifikat',
        };
    }
}
