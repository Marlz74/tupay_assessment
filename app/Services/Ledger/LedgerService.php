<?php

namespace App\Services\Ledger;

use App\Enums\WalletType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

final class LedgerService
{
    /**
     * @return LengthAwarePaginator<int, LedgerEntry>
     */
    public function listWalletLedgers(
        User $user,
        string $walletId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $wallet = Wallet::whereKey($walletId)->first();

        if ($wallet === null) {
            throw (new ModelNotFoundException)->setModel(Wallet::class, [$walletId]);
        }

        if ($wallet->type !== WalletType::User || $wallet->user_id !== $user->id) {
            throw new AuthorizationException('Unauthorized. You are not allowed to view this wallet ledger.');
        }

        $perPage = max(1, min($perPage, 100));

        return LedgerEntry::with('currency')
            ->with('currency')
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
