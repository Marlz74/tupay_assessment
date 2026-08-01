<?php

namespace App\Services\Wallet;

use App\Enums\WalletType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Collection;

final class WalletService
{
    /** @return Collection<int, Wallet> */
    public function listWallets(User $user): Collection
    {
        return Wallet::with('currency')
            ->leftJoin('wallet_balances', 'wallet_balances.wallet_id', '=', 'wallets.id')
            ->where('wallets.user_id', $user->id)
            ->where('wallets.type', WalletType::User)
            ->orderBy('wallets.slug')
            ->select([
                'wallets.*',
                'wallet_balances.balance_subunits',
            ])
            ->get();
    }
}
