<?php

namespace App\Enums;

enum FundStatus: string
{
    case Ditahan = 'ditahan';
    case Diteruskan = 'diteruskan';
    case Dicairkan = 'dicairkan';
    case Dikembalikan = 'dikembalikan';

    public function label(): string
    {
        return match ($this) {
            self::Ditahan => 'Ditahan Sejuk',
            self::Diteruskan => 'Diteruskan ke teknisi',
            self::Dicairkan => 'Sudah dicairkan',
            self::Dikembalikan => 'Dikembalikan ke pelanggan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Ditahan => 'sun',
            self::Diteruskan => 'ice',
            self::Dicairkan => 'leaf',
            self::Dikembalikan => 'mute',
        };
    }
}
