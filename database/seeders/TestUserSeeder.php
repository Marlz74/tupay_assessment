<?php

namespace Database\Seeders;

use App\DTO\Ledger\LedgerLegDTO;
use App\Enums\LedgerEntryType;
use App\Enums\WalletType;
use App\Models\Currency;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\PostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    public const EMAIL = 'test@tupay.test';

    public const PASSWORD = 'password';

    /** 50,000,000 NGN in kobo */
    public const OPENING_NGN_KOBO = 5000_000_000;

    public function run(): void
    {
        $ngn = Currency::where('code', 'NGN')->firstOrFail();
        $cny = Currency::where('code', 'CNY')->firstOrFail();

        $user = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'firstname' => 'Test',
                'lastname' => 'User',
                'password' => Hash::make(self::PASSWORD),
                'totp_secret' => self::TOTP_SECRET,
                'totp_confirmed_at' => now(),
                'email_verified_at' => now(),
            ],
        );

        $userNgn = Wallet::updateOrCreate(
            ['slug' => Wallet::slugForUser($user->id, 'NGN')],
            [
                'user_id' => $user->id,
                'currency_id' => $ngn->id,
                'type' => WalletType::User,
            ],
        );

        Wallet::updateOrCreate(
            ['slug' => Wallet::slugForUser($user->id, 'CNY')],
            [
                'user_id' => $user->id,
                'currency_id' => $cny->id,
                'type' => WalletType::User,
            ],
        );

        if ($userNgn->ledgerEntries()->exists()) {
            $this->command?->info('Test user already funded; skip funding.');

            return;
        }

        $clearingNgn = Wallet::where('slug', Wallet::slugForSystem(WalletType::Clearing, 'NGN'))
            ->firstOrFail();

        $amount = self::OPENING_NGN_KOBO;

        app(PostingService::class)->post(
            legs: [
                new LedgerLegDTO(
                    walletId: $clearingNgn->id,
                    currencyId: $ngn->id,
                    amount: -$amount,
                    entryType: LedgerEntryType::SeedDebit,
                    description: 'Opening balance mint',
                ),
                new LedgerLegDTO(
                    walletId: $userNgn->id,
                    currencyId: $ngn->id,
                    amount: $amount,
                    entryType: LedgerEntryType::SeedCredit,
                    description: 'Opening balance',
                ),
            ],
            wallets: [$clearingNgn, $userNgn],
        );

        $this->command?->info('Test user ready:');
        $this->command?->info('email: '.self::EMAIL);
        $this->command?->info('password: '.self::PASSWORD);
        $this->command?->info('totp_secret: '.self::TOTP_SECRET);
        $this->command?->info('NGN wallet: '.$userNgn->id);
    }
}
