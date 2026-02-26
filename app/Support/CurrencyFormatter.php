<?php

namespace App\Support;

class CurrencyFormatter
{
    public static function symbol(?string $currencyCode): string
    {
        return match (strtoupper((string) $currencyCode)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$',
            default => '$',
        };
    }

    public static function format(float|int|string|null $amount, ?string $currencyCode): string
    {
        $value = (float) ($amount ?? 0);

        return self::symbol($currencyCode).number_format($value, 2);
    }
}
