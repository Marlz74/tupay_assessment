<?php

namespace App\Http\Resources;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

class WalletResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $wallet = $this->resource;

        if (! $wallet instanceof Wallet) {
            throw new RuntimeException('WalletResource expects a Wallet resource.');
        }

        return [
            'id' => $wallet->id,
            'slug' => $wallet->slug,
            'type' => $wallet->type->value,
            'currency' => $wallet->currency?->code,
            'currency_id' => $wallet->currency_id,
            'balance_subunits' => (int) ($wallet->balance_subunits ?? 0),
        ];
    }
}
