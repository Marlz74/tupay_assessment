<?php

namespace App\DTO\Ledger;

use App\Enums\LedgerEntryType;

final readonly class LedgerLegDTO
{
    public function __construct(
        public string $walletId,
        public string $currencyId,
        public int $amount,
        public LedgerEntryType $entryType,
        public ?string $description = null,
        public ?string $referenceType = null,
        public ?string $referenceId = null,
    ) {}
}
