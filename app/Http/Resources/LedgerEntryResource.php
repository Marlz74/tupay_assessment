<?php

namespace App\Http\Resources;

use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

class LedgerEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        if (! $entry instanceof LedgerEntry) {
            throw new RuntimeException('LedgerEntryResource expects a LedgerEntry resource.');
        }

        return [
            'id' => $entry->id,
            'transaction_id' => $entry->transaction_id,
            'wallet_id' => $entry->wallet_id,
            'currency' => $entry->currency?->code,
            'currency_id' => $entry->currency_id,
            'amount' => $entry->amount,
            'entry_type' => $entry->entry_type?->value,
            'description' => $entry->description,
            'reference_type' => $entry->reference_type,
            'reference_id' => $entry->reference_id,
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }
}
