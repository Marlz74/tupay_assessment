<?php

return [
    'fx' => [
        'fresh_seconds' => (int) env('FX_FRESH_SECONDS', 30),
        'stale_seconds' => (int) env('FX_STALE_SECONDS', 300),
        'rates' => [
            'NGN_CNY' => env('FX_NGN_CNY', '0.0085'),
            'CNY_NGN' => env('FX_CNY_NGN', '117.647058823529'),
        ],
        'mock_url' => env('FX_MOCK_URL', ''),
    ],
    'swap' => [
        'lock_seconds' => 10,
        'lock_wait_seconds' => 3,
        'slippage_threshold_subunits' => 100_000_000, // 1,000,000 NGN in kobo, Amount that will trigger a slippage warning
        'slippage_block_subunits' => 50_000_000,      // 500,000 NGN in kobo, Amount that will trigger a slippage block
        'slippage_base_percent' => '0.5',
        'slippage_step_percent' => '0.1',
    ],

    'webhook_secret' => env('WEBHOOK_SECRET', ''),
];
