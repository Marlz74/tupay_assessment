<?php

namespace App\Actions;

use App\DTO\Ledger\LedgerLegDTO;
use App\Enums\LedgerEntryType;
use App\Enums\WalletType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\PostingService;
use App\Services\Locking\SwapLockService;
use App\Services\Swap\RateEngineService;
use App\Services\Swap\SlippageCalculatorService;
use App\Support\MoneyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Log;

final class ExecuteSwapAction
{
    public function __construct(
        private readonly RateEngineService $rateEngineService,
        private readonly SlippageCalculatorService $slippageCalculatorService,
        private readonly SwapLockService $swapLockService,
        private readonly PostingService $postingService,
    ) {}

    /**
     * @return array{
     *   transaction_id: string,
     *   source_amount: int,
     *   fee_amount: int,
     *   destination_amount: int,
     *   rate: string,
     *   source_currency: string,
     *   destination_currency: string
     * }
     */
    public function execute(
        User $user,
        string $sourceWalletId,
        string $destinationWalletId,
        int $amount,
    ): array {
        if ($sourceWalletId === $destinationWalletId) {
            throw ValidationException::withMessages([
                'destination_wallet_id' => ['Source and destination wallets must differ.'],
            ]);
        }

        $userWallets = Wallet::with('currency')
            ->whereIn('id', [$sourceWalletId, $destinationWalletId])
            ->get()
            ->keyBy('id');

        $sourceWallet = $userWallets->get($sourceWalletId);
        $destinationWallet = $userWallets->get($destinationWalletId);

        if ($sourceWallet === null) {

            Log::warning('Source wallet not found for user.', [
                'user_id' => $user->id,
                'source_wallet_id' => $sourceWalletId,
            ]);

            throw ValidationException::withMessages([
                'source_wallet_id' => ['Source Wallet not found for this user.'],
            ]);
        }
        if ($destinationWallet === null) {
            Log::warning('Destination wallet not found for user.', [
                'user_id' => $user->id,
                'destination_wallet_id' => $destinationWalletId,
            ]);
            throw ValidationException::withMessages([
                'destination_wallet_id' => ['Destination Wallet not found for this user.'],
            ]);
        }

        $this->assertUserWallet($user, $sourceWallet, 'source_wallet_id');
        $this->assertUserWallet($user, $destinationWallet, 'destination_wallet_id');

        $sourceCurrency = $sourceWallet->currency;
        $destinationCurrency = $destinationWallet->currency;

        if ($sourceCurrency === null || $destinationCurrency === null) {
            throw ValidationException::withMessages([
                'amount' => ['Wallet currency is not configured.'],
            ]);
        }

        $baseCurrency = $sourceCurrency->code;
        $quoteCurrency = $destinationCurrency->code;
        if ($baseCurrency === $quoteCurrency) {
            throw ValidationException::withMessages([
                'destination_wallet_id' => ['Wallets must be different currencies.'],
            ]);
        }

        $rate = $this->rateEngineService->getRate($baseCurrency, $quoteCurrency);
        $fee = $this->slippageCalculatorService->feeSubunits($amount);
        $destinationAmount = MoneyMath::convert($amount, $rate);
        $totalDebit = MoneyMath::add($amount, $fee);

        if ($destinationAmount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['Converted amount rounds to zero.'],
            ]);
        }

        $clearingSourceSlug = Wallet::slugForSystem(WalletType::Clearing, $baseCurrency);
        $clearingDestSlug = Wallet::slugForSystem(WalletType::Clearing, $quoteCurrency);
        $treasurySlug = Wallet::slugForSystem(WalletType::Treasury, $baseCurrency);

        $systemWallets = Wallet::whereIn('slug', [$clearingSourceSlug, $clearingDestSlug, $treasurySlug])
            ->get()
            ->keyBy('slug');

        $clearingSourceWallet = $systemWallets->get($clearingSourceSlug);
        $clearingDestWallet = $systemWallets->get($clearingDestSlug);
        $treasuryWallet = $systemWallets->get($treasurySlug);

        if ($clearingSourceWallet === null || $clearingDestWallet === null || $treasuryWallet === null) {
            throw ValidationException::withMessages([
                'amount' => ['System wallets are not configured for this currency pair.'],
            ]);
        }

        $this->swapLockService->acquire($user->id, [$sourceWallet->id, $destinationWallet->id]);

        try {
            $transactionId = DB::transaction(function () use (
                $sourceWallet,
                $destinationWallet,
                $clearingSourceWallet,
                $clearingDestWallet,
                $treasuryWallet,
                $amount,
                $fee,
                $totalDebit,
                $destinationAmount,
                $baseCurrency,
                $quoteCurrency,
            ): string {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                }

                $walletIds = [
                    $sourceWallet->id,
                    $destinationWallet->id,
                    $clearingSourceWallet->id,
                    $clearingDestWallet->id,
                    $treasuryWallet->id,
                ];
                $walletIds = array_values(array_unique($walletIds));
                sort($walletIds, SORT_STRING);

                // lock every participating wallet
                $lockedWallet = Wallet::whereIn('id', $walletIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($lockedWallet->count() !== count($walletIds)) {
                    throw new InsufficientBalanceException('One or more wallets could not be locked.');
                }

                $balance = $this->walletBalance($sourceWallet->id);
                if ($balance < $totalDebit) {
                    throw new InsufficientBalanceException('Insufficient wallet balance for swap.');
                }

                $legs = [
                    new LedgerLegDTO(
                        walletId: $sourceWallet->id,
                        currencyId: $sourceWallet->currency_id,
                        amount: -$totalDebit,
                        entryType: LedgerEntryType::SwapDebit,
                        description: "Swap debit {$baseCurrency}",
                    ),
                    new LedgerLegDTO(
                        walletId: $clearingSourceWallet->id,
                        currencyId: $clearingSourceWallet->currency_id,
                        amount: $amount,
                        entryType: LedgerEntryType::SwapClearingCredit,
                        description: "Swap clearing {$baseCurrency}",
                    ),
                ];

                if ($fee > 0) {
                    $legs[] = new LedgerLegDTO(
                        walletId: $treasuryWallet->id,
                        currencyId: $treasuryWallet->currency_id,
                        amount: $fee,
                        entryType: LedgerEntryType::SwapFee,
                        description: "Swap fee {$baseCurrency}",
                    );
                }

                $legs[] = new LedgerLegDTO(
                    walletId: $clearingDestWallet->id,
                    currencyId: $clearingDestWallet->currency_id,
                    amount: -$destinationAmount,
                    entryType: LedgerEntryType::SwapClearingDebit,
                    description: "Swap clearing {$quoteCurrency}",
                );

                $legs[] = new LedgerLegDTO(
                    walletId: $destinationWallet->id,
                    currencyId: $destinationWallet->currency_id,
                    amount: $destinationAmount,
                    entryType: LedgerEntryType::SwapCredit,
                    description: "Swap credit {$quoteCurrency}",
                );

                return $this->postingService->post(
                    legs: $legs,
                    wallets: $lockedWallet,
                );
            });
        } finally {
            $this->swapLockService->release();
        }

        return [
            'transaction_id' => $transactionId,
            'source_amount' => $amount,
            'fee_amount' => $fee,
            'destination_amount' => $destinationAmount,
            'rate' => $rate,
            'source_currency' => $baseCurrency,
            'destination_currency' => $quoteCurrency,
        ];
    }

    private function assertUserWallet(User $user, Wallet $wallet, string $field): void
    {
        if ($wallet->type !== WalletType::User || $wallet->user_id !== $user->id) {
            throw ValidationException::withMessages([
                $field => ['Wallet not found for this user.'],
            ]);
        }
    }

    private function walletBalance(string $walletId): int
    {
        return (int) LedgerEntry::where('wallet_id', $walletId)
            ->sum('amount');
    }
}
