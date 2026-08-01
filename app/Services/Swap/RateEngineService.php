<?php

namespace App\Services\Swap;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

final class RateEngineService
{
    /**
     * Return FX rate as string.
     */
    public function getRate(string $base, string $quote): string
    {
        $base = strtoupper($base);
        $quote = strtoupper($quote);

        if ($base === $quote) {
            throw new RuntimeException('Base and quote currency must differ.');
        }

        $cacheKey = "fx:{$base}:{$quote}";
        $freshTtl = (int) config('tupay.fx.fresh_seconds', 30);
        $staleTtl = (int) config('tupay.fx.stale_seconds', 300);

        $cached = Redis::get($cacheKey);
        if (is_string($cached) && $cached !== '') {

            $cachedRate = json_decode($cached, true);
            $rate = $cachedRate['rate'] ?? null;
            $fetchedAt = (int) ($cachedRate['fetched_at'] ?? 0);

            if (is_string($rate) && is_numeric($rate)) {
                $age = time() - $fetchedAt;

                if ($age <= $freshTtl) {
                    return $rate;
                }

                if ($age <= $staleTtl) {
                    try {
                        return $this->fetchAndStore($base, $quote, $cacheKey, $staleTtl);
                    } catch (\Throwable) {
                        return $rate;
                    }
                }
            }
        }

        return $this->fetchAndStore($base, $quote, $cacheKey, $staleTtl);
    }

    private function fetchAndStore(string $base, string $quote, string $cacheKey, int $staleTtl): string
    {
        $rate = $this->fetchRate($base, $quote);

        Redis::setex($cacheKey, $staleTtl, json_encode([
            'rate' => $rate,
            'fetched_at' => time(),
        ], JSON_THROW_ON_ERROR));

        return $rate;
    }

    private function fetchRate(string $base, string $quote): string
    {
        $pairKey = "{$base}_{$quote}";
        $configuredRate = config("tupay.fx.rates.{$pairKey}");

        if (is_string($configuredRate) && is_numeric($configuredRate)) {
            return $configuredRate;
        }

        // Optional mock HTTP falls back if not configured
        $mockUrl = config('tupay.fx.mock_url');
        if (is_string($mockUrl) && $mockUrl !== '') {
            $response = Http::timeout(3)->get($mockUrl, [
                'base' => $base,
                'quote' => $quote,
            ]);

            if ($response->successful() && is_numeric($response->json('rate'))) {
                return (string) $response->json('rate');
            }
        }

        throw new RuntimeException("No FX rate configured for {$base}/{$quote}.");
    }
}
