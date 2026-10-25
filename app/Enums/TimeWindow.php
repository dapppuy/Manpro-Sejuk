<?php

namespace App\Enums;

enum TimeWindow: string
{
    case Pagi = 'pagi';
    case Siang = 'siang';
    case Sore = 'sore';
    case Malam = 'malam';

    public function label(): string
    {
        return match ($this) {
            self::Pagi => 'Pagi',
            self::Siang => 'Siang',
            self::Sore => 'Sore',
            self::Malam => 'Malam',
        };
    }

    public function range(): string
    {
        return match ($this) {
            self::Pagi => '08.00–11.00',
            self::Siang => '11.00–14.00',
            self::Sore => '14.00–17.00',
            self::Malam => '17.00–20.00',
        };
    }

    public function startHour(): int
    {
        return match ($this) {
            self::Pagi => 8,
            self::Siang => 11,
            self::Sore => 14,
            self::Malam => 17,
        };
    }
}
