<?php

namespace Tests\Support;

use App\DTO\Ledger\LedgerLegDTO;
use App\Enums\LedgerEntryType;
use App\Enums\WalletType;
use App\Models\Currency;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\PostingService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

trait CreatesLedgerFixture
{
    protected User $fixtureUser;

    protected Wallet $ngnWallet;

    protected Wallet $cnyWallet;

    protected Currency $ngn;

    protected Currency $cny;

    protected function seedCurrenciesAndSystemWallets(): void
    {
        $this->ngn = Currency::query()->create(['code' => 'NGN', 'logo' => null]);
        $this->cny = Currency::query()->create(['code' => 'CNY', 'logo' => null]);

        foreach (
            [
                [WalletType::Clearing, $this->ngn],
                [WalletType::Clearing, $this->cny],
                [WalletType::Treasury, $this->ngn],
            ] as [$type, $currency]
        ) {
            Wallet::query()->create([
                'user_id' => null,
                'currency_id' => $currency->id,
                'type' => $type,
                'slug' => Wallet::slugForSystem($type, $currency->code),
            ]);
        }
    }

    protected function seedUserWithWallets(int $ngnBalanceSubunits = 1_000_000): string
    {
        $this->fixtureUser = User::query()->create([
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'feature@tupay.test',
            'password' => Hash::make('password'),
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'totp_confirmed_at' => now(),
            'email_verified_at' => now(),
        ]);

        $this->ngnWallet = Wallet::query()->create([
            'user_id' => $this->fixtureUser->id,
            'currency_id' => $this->ngn->id,
            'type' => WalletType::User,
            'slug' => Wallet::slugForUser($this->fixtureUser->id, 'NGN'),
        ]);

        $this->cnyWallet = Wallet::query()->create([
            'user_id' => $this->fixtureUser->id,
            'currency_id' => $this->cny->id,
            'type' => WalletType::User,
            'slug' => Wallet::slugForUser($this->fixtureUser->id, 'CNY'),
        ]);

        if ($ngnBalanceSubunits > 0) {
            $clearing = Wallet::query()
                ->where('slug', Wallet::slugForSystem(WalletType::Clearing, 'NGN'))
                ->firstOrFail();

            $this->app->make(PostingService::class)->post(
                legs: [
                    new LedgerLegDTO(
                        walletId: $clearing->id,
                        currencyId: $this->ngn->id,
                        amount: -$ngnBalanceSubunits,
                        entryType: LedgerEntryType::SeedDebit,
                        description: 'Feature fixture mint',
                    ),
                    new LedgerLegDTO(
                        walletId: $this->ngnWallet->id,
                        currencyId: $this->ngn->id,
                        amount: $ngnBalanceSubunits,
                        entryType: LedgerEntryType::SeedCredit,
                        description: 'Feature fixture mint',
                    ),
                ],
                wallets: [$clearing, $this->ngnWallet],
            );
        }

        return $this->fixtureUser->createToken('feature')->plainTextToken;
    }

    /** @return array{source_wallet_id: string, destination_wallet_id: string, amount: int} */
    protected function swapPayload(int $amount = 10_000): array
    {
        return [
            'source_wallet_id' => $this->ngnWallet->id,
            'destination_wallet_id' => $this->cnyWallet->id,
            'amount' => $amount,
        ];
    }

    protected function requireRedis(): void
    {
        try {
            Redis::connection()->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis unavailable: '.$e->getMessage());
        }
    }
}
