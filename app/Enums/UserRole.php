<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Technician = 'technician';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Pelanggan',
            self::Technician => 'Teknisi',
            self::Admin => 'Admin',
        };
    }
}
