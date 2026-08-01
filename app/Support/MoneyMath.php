<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Subunit money math using BCMath only.
 * Final outputs are logged in the ledger as ints (kobo / fen).
 */
final class MoneyMath
{
    private const INTERNAL_SCALE = 12;

    public static function add(int $a, int $b): int
    {
        return self::toInt(bcadd((string) $a, (string) $b, 0));
    }

    public static function sub(int $a, int $b): int
    {
        return self::toInt(bcsub((string) $a, (string) $b, 0));
    }

    public static function multiply(int $amountSubunits, string $factor): int
    {
        $factor = self::asNumericString($factor, 'factor');

        $product = bcmul((string) $amountSubunits, $factor, self::INTERNAL_SCALE);

        return self::roundHalfEvenToInt($product);
    }

    public static function percentOf(int $amountSubunits, string $percent): int
    {
        $percent = self::asNumericString($percent, 'percent');

        $decimalFraction = bcdiv($percent, '100', self::INTERNAL_SCALE);

        return self::multiply($amountSubunits, $decimalFraction);
    }

    public static function convert(int $sourceSubunits, string $rate): int
    {
        if ($sourceSubunits < 0) {
            throw new InvalidArgumentException('sourceSubunits must be greater than 0');
        }

        return self::multiply($sourceSubunits, $rate);
    }

    /**
     * Banker's rounding (ROUND_HALF_EVEN) of a BCMath decimal string → int.
     *
     * Halfway cases (.5 exactly) round toward the nearest even integer:
     *   2.5 → 2,  3.5 → 4,  -2.5 → -2
     */
    public static function roundHalfEvenToInt(string $number): int
    {
        $number = self::asNumericString($number, 'number');
        self::ensureBcMath();

        $negative = str_starts_with($number, '-');
        $abs = ltrim($number, '+-');

        if (! str_contains($abs, '.')) {
            return self::toInt($negative ? '-'.$abs : $abs);
        }

        [$whole, $fraction] = explode('.', $abs, 2);
        $whole = $whole === '' ? '0' : $whole;

        $fraction = rtrim($fraction, '0');
        if ($fraction === '') {
            return self::toInt($negative ? '-'.$whole : $whole);
        }

        $firstDecimal = (int) $fraction[0];
        $rest = substr($fraction, 1);
        $restHasNonZero = $rest !== '' && trim($rest, '0') !== '';

        if ($firstDecimal < 5) {
            return self::toInt($negative ? '-'.$whole : $whole);
        }

        if ($firstDecimal > 5 || $restHasNonZero) {
            // round upward, add 1 to whole
            $rounded = bcadd(self::asNumericString($whole, 'whole'), '1', 0);

            return self::toInt($negative ? '-'.$rounded : $rounded);
        }

        // if exactly .5 round to nearest even number, even if whole % 2 == 0
        if (bcmod($whole, '2') === '0') {
            return self::toInt($negative ? '-'.$whole : $whole);
        }

        $rounded = bcadd(self::asNumericString($whole, 'whole'), '1', 0);

        return self::toInt($negative ? '-'.$rounded : $rounded);
    }

    private static function toInt(string $value): int
    {
        if (! preg_match('/^-?\d+$/', $value)) {
            throw new InvalidArgumentException("Expected integer string, got [{$value}]");
        }

        return (int) $value;
    }

    /** @return numeric-string */
    private static function asNumericString(string $value, string $label): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("{$label} must be numeric, got [{$value}]");
        }

        return $value;
    }

    private static function ensureBcMath(): void
    {
        if (! extension_loaded('bcmath')) {
            throw new RuntimeException('ext-bcmath is required for money calculations');
        }
    }
}
