<?php

namespace Database\Seeders;

use App\Enums\WalletType;
use App\Models\Currency;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class SystemWalletSeeder extends Seeder
{
    public function run(): void
    {
        $ngn = Currency::where('code', 'NGN')->firstOrFail();
        $cny = Currency::where('code', 'CNY')->firstOrFail();

        $systemWallets = [
            [WalletType::Clearing, $ngn],
            [WalletType::Clearing, $cny],
            [WalletType::Treasury, $ngn],
        ];

        foreach ($systemWallets as [$type, $currency]) {
            Wallet::updateOrCreate(
                ['slug' => Wallet::slugForSystem($type, $currency->code)],
                [
                    'user_id' => null,
                    'currency_id' => $currency->id,
                    'type' => $type,
                ],
            );
        }
    }
}
