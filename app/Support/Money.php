<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public const MAX_CENTAVOS = 99_999_999_99;

    public static function toCentavos(string|int|float $amount): int
    {
        if (is_int($amount)) {
            return $amount * 100;
        }

        $raw = trim((string) $amount);
        if ($raw === '' || ! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $raw)) {
            throw new InvalidArgumentException("Invalid monetary amount: {$raw}");
        }

        $negative = str_starts_with($raw, '-');
        if ($negative) {
            $raw = substr($raw, 1);
        }

        [$whole, $frac] = array_pad(explode('.', $raw, 2), 2, '');
        $frac = str_pad($frac, 2, '0', STR_PAD_RIGHT);

        $centavos = ((int) $whole * 100) + (int) $frac;

        return $negative ? -$centavos : $centavos;
    }

    public static function fromCentavos(int $centavos): string
    {
        $negative = $centavos < 0;
        $abs = abs($centavos);
        $whole = intdiv($abs, 100);
        $cents = $abs % 100;

        return ($negative ? '-' : '').$whole.'.'.str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
    }

    public static function formatPhp(string|int|float|null $amount): string
    {
        $centavos = $amount === null ? 0 : self::toCentavos((string) $amount);

        return self::formatCentavos($centavos);
    }

    public static function formatCentavos(int $centavos): string
    {
        $symbol = (string) config('pos.currency_symbol', '₱');
        $negative = $centavos < 0;
        $abs = abs($centavos);
        $formatted = number_format($abs / 100, 2, '.', ',');

        return ($negative ? '-' : '').$symbol.$formatted;
    }
}
