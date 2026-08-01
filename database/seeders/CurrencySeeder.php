<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::updateOrCreate(
            ['code' => 'NGN'],
            ['logo' => null],
        );

        Currency::updateOrCreate(
            ['code' => 'CNY'],
            ['logo' => null],
        );
    }
}
