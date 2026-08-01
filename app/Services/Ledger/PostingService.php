<?php

namespace App\Services\Ledger;

use App\DTO\Ledger\LedgerLegDTO;
use App\Exceptions\InvalidLedgerPostingException;
use App\Exceptions\UnbalancedLedgerException;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Support\MoneyMath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PostingService
{
    /**
     * Persist a balanced double-entry.
     *
     * @param  list<LedgerLegDTO>  $legs
     * @param  iterable<int, Wallet>|Collection<string|int, Wallet>  $wallets
     */
    public function post(
        array $legs,
        ?string $transactionId = null,
        iterable $wallets = [],
    ): string {
        $this->assertValidLegs($legs);
        $this->assertBalancedPerCurrency($legs);
        $this->assertWalletsMatchCurrencies($legs, $wallets);

        $transactionId ??= (string) Str::uuid();
        $now = now();

        $rows = array_map(static fn (LedgerLegDTO $leg): array => [
            'id' => (string) Str::uuid(),
            'transaction_id' => $transactionId,
            'wallet_id' => $leg->walletId,
            'currency_id' => $leg->currencyId,
            'amount' => $leg->amount,
            'entry_type' => $leg->entryType->value,
            'description' => $leg->description,
            'reference_type' => $leg->referenceType,
            'reference_id' => $leg->referenceId,
            'created_at' => $now,
        ], $legs);

        DB::transaction(static function () use ($rows): void {
            LedgerEntry::insert($rows);
        });

        return $transactionId;
    }

    /** @param  list<LedgerLegDTO>  $legs */
    private function assertValidLegs(array $legs): void
    {
        if (count($legs) < 2) {
            throw new InvalidLedgerPostingException('A posting requires at least 2 legs.');
        }

        foreach ($legs as $leg) {
            if ($leg->amount === 0) {
                throw new InvalidLedgerPostingException('Ledger leg amount cannot be zero.');
            }
        }
    }

    /** @param  list<LedgerLegDTO>  $legs */
    private function assertBalancedPerCurrency(array $legs): void
    {
        /** @var array<string, int> $totals */
        $totals = [];

        foreach ($legs as $leg) {
            $totals[$leg->currencyId] = MoneyMath::add(
                $totals[$leg->currencyId] ?? 0,
                $leg->amount,
            );
        }

        foreach ($totals as $currencyId => $sum) {
            if ($sum !== 0) {
                throw new UnbalancedLedgerException(
                    "Unbalanced posting for currency [{$currencyId}]: sum={$sum}",
                );
            }
        }
    }

    /**
     * @param  list<LedgerLegDTO>  $legs
     * @param  iterable<int, Wallet>|Collection<string|int, Wallet>  $wallets
     */
    private function assertWalletsMatchCurrencies(array $legs, iterable $wallets = []): void
    {
        $walletIds = array_values(array_unique(array_map(
            static fn (LedgerLegDTO $leg): string => $leg->walletId,
            $legs,
        )));

        $walletsById = collect($wallets)
            ->filter(static fn (mixed $wallet): bool => $wallet instanceof Wallet)
            ->keyBy(static fn (Wallet $wallet): string => $wallet->id);

        if ($walletsById->isEmpty()) {
            $walletsById = Wallet::whereIn('id', $walletIds)
                ->get(['id', 'currency_id'])
                ->keyBy('id');
        }

        foreach ($walletIds as $walletId) {
            if (! $walletsById->has($walletId)) {
                throw new InvalidLedgerPostingException("Wallet [{$walletId}] not found.");
            }
        }

        foreach ($legs as $leg) {
            /** @var Wallet $wallet */
            $wallet = $walletsById->get($leg->walletId);

            if ($wallet->currency_id !== $leg->currencyId) {
                throw new InvalidLedgerPostingException(
                    "Wallet [{$leg->walletId}] currency mismatch.",
                );
            }
        }
    }
}
