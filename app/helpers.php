<?php

if (! function_exists('rupiah')) {
    /**
     * Format an amount as Indonesian Rupiah, e.g. "Rp125.000".
     */
    function rupiah(int|float|null $amount): string
    {
        return 'Rp'.number_format((float) ($amount ?? 0), 0, ',', '.');
    }
}

if (! function_exists('rupiah_range')) {
    /**
     * Format a price range, e.g. "Rp65.000–Rp80.000".
     */
    function rupiah_range(int|float|null $minimum, int|float|null $maximum): string
    {
        if ($minimum === $maximum || $maximum === null) {
            return rupiah($minimum);
        }

        return rupiah($minimum).'–'.rupiah($maximum);
    }
}
