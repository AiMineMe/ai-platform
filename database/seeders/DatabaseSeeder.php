<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            CurrencySeeder::class,
            TradeSettingsSeeder::class,
            IcoTokenSeeder::class,
            PaymentGatewaySeeder::class,
            WithdrawalGatewaySeeder::class,
            MenuSeeder::class,
            LanguageSeeder::class,
            BlogSeeder::class,
            MiningSeeder::class,
            SubscriptionPlanSeeder::class,
        ]);
    }
}
