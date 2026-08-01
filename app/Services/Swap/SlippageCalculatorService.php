<?php

namespace App\Services\Swap;

use App\Support\MoneyMath;

final class SlippageCalculatorService
{
    /**
     * Fee in source-currency subunits.
     * If amountSubunits exceeds threshold, return 0.5% base + 0.1% per full additional block.
     */
    public function feeSubunits(int $amountSubunits): int
    {
        $threshold = (int) config('tupay.swap.slippage_threshold_subunits', 100_000_000);
        $block = (int) config('tupay.swap.slippage_block_subunits', 50_000_000);
        $basePercent = $this->numericString(config('tupay.swap.slippage_base_percent'), '0.5');
        $stepPercent = $this->numericString(config('tupay.swap.slippage_step_percent'), '0.1');

        if ($amountSubunits <= $threshold) {
            return 0;
        }

        $extraBlocks = intdiv($amountSubunits - $threshold, $block);
        $percent = bcadd(
            $basePercent,
            bcmul($stepPercent, (string) $extraBlocks, 12),
            12,
        );

        return MoneyMath::percentOf($amountSubunits, $percent);
    }

    /** @return numeric-string */
    private function numericString(mixed $value, string $fallback): string
    {
        $string = is_scalar($value) ? (string) $value : null;

        if ($string !== null && is_numeric($string)) {
            return $string;
        }

        if (is_numeric($fallback)) {
            return $fallback;
        }

        return '0';
    }
}
