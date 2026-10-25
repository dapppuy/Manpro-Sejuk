<?php

namespace App\Enums;

/**
 * The nine payment methods from the design. OVO and DANA have no Midtrans Core API channel,
 * so they are charged as QRIS and the customer scans the code from that app.
 */
enum PaymentMethod: string
{
    case Qris = 'qris';
    case BcaVa = 'bca_va';
    case BniVa = 'bni_va';
    case BriVa = 'bri_va';
    case MandiriVa = 'mandiri_va';
    case Gopay = 'gopay';
    case Ovo = 'ovo';
    case Dana = 'dana';
    case Shopeepay = 'shopeepay';

    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
            self::BcaVa => 'BCA Virtual Account',
            self::BniVa => 'BNI Virtual Account',
            self::BriVa => 'BRI Virtual Account',
            self::MandiriVa => 'Mandiri Virtual Account',
            self::Gopay => 'GoPay',
            self::Ovo => 'OVO',
            self::Dana => 'DANA',
            self::Shopeepay => 'ShopeePay',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Qris => 'Scan dari m-banking atau e-wallet apa pun',
            self::BcaVa, self::BniVa, self::BriVa, self::MandiriVa => 'Dicek otomatis',
            self::Gopay, self::Shopeepay => 'Bayar di aplikasi',
            self::Ovo, self::Dana => 'Scan QRIS dari aplikasi',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
            self::BcaVa, self::BniVa, self::BriVa, self::MandiriVa => 'Virtual Account',
            default => 'E-wallet',
        };
    }

    public function groupIcon(): string
    {
        return match ($this->group()) {
            'QRIS' => 'qr-code',
            'Virtual Account' => 'landmark',
            default => 'smartphone',
        };
    }

    public function isVirtualAccount(): bool
    {
        return $this->group() === 'Virtual Account';
    }

    /**
     * Methods shown as a scannable QR code.
     */
    public function usesQr(): bool
    {
        return in_array($this, [self::Qris, self::Ovo, self::Dana, self::Gopay], true);
    }

    /**
     * The method-specific part of a Midtrans Core API charge request.
     *
     * @return array<string, mixed>
     */
    public function chargeParameters(string $orderCode, string $callbackUrl): array
    {
        return match ($this) {
            self::Qris, self::Ovo, self::Dana => ['payment_type' => 'qris', 'qris' => ['acquirer' => 'gopay']],
            self::BcaVa => ['payment_type' => 'bank_transfer', 'bank_transfer' => ['bank' => 'bca']],
            self::BniVa => ['payment_type' => 'bank_transfer', 'bank_transfer' => ['bank' => 'bni']],
            self::BriVa => ['payment_type' => 'bank_transfer', 'bank_transfer' => ['bank' => 'bri']],
            self::MandiriVa => ['payment_type' => 'echannel', 'echannel' => ['bill_info1' => 'Pembayaran:', 'bill_info2' => 'Sejuk '.$orderCode]],
            self::Gopay => ['payment_type' => 'gopay', 'gopay' => ['enable_callback' => true, 'callback_url' => $callbackUrl]],
            self::Shopeepay => ['payment_type' => 'shopeepay', 'shopeepay' => ['callback_url' => $callbackUrl]],
        };
    }

    /**
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $method) {
            $groups[$method->group()][] = $method;
        }

        return $groups;
    }
}
