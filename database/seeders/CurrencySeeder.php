<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            $currencyService = new CurrencyService();
            $currencyService->updateAllPrices();
        } catch (\Exception $e) {
            $this->command->error('Currency seeder failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
