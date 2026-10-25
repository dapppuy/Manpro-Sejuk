<?php

namespace App\Enums;

/**
 * Outcome of offering an order to one technician.
 */
enum OfferStatus: string
{
    case Menunggu = 'menunggu';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';
    case Kedaluwarsa = 'kedaluwarsa';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu respons',
            self::Diterima => 'Penawaran dikirim',
            self::Ditolak => 'Ditolak',
            self::Kedaluwarsa => 'Tidak direspons',
        };
    }
}
