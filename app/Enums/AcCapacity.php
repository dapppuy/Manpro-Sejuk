<?php

namespace App\Enums;

enum AcCapacity: string
{
    case Half = '0.5';
    case ThreeQuarter = '0.75';
    case One = '1';
    case OneAndHalf = '1.5';
    case Two = '2';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Half => '0,5 PK',
            self::ThreeQuarter => '0,75 PK',
            self::One => '1 PK',
            self::OneAndHalf => '1,5 PK',
            self::Two => '2 PK',
            self::Unknown => 'Tidak tahu',
        };
    }
}
