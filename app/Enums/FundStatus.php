<?php

namespace App\Enums;

enum FundStatus: string
{
    case Ditahan = 'ditahan';
    case Diteruskan = 'diteruskan';
    case Dicairkan = 'dicairkan';

    public function label(): string
    {
        return match ($this) {
            self::Ditahan => 'Ditahan Sejuk',
            self::Diteruskan => 'Diteruskan ke teknisi',
            self::Dicairkan => 'Sudah dicairkan',
        };
    }
}
