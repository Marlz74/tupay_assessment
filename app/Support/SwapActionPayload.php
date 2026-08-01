<?php

namespace App\Support;

final class SwapActionPayload
{
    /** @return list<string> */
    public static function keys(): array
    {
        return [
            'source_wallet_id',
            'destination_wallet_id',
            'amount',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function extract(array $input): array
    {
        $output = [];
        foreach (self::keys() as $key) {
            if (array_key_exists($key, $input)) {
                $output[$key] = $input[$key];
            }
        }

        // amount must be int subunits for stable hashing
        if (isset($output['amount'])) {
            $output['amount'] = (int) $output['amount'];
        }

        return $output;
    }
}
