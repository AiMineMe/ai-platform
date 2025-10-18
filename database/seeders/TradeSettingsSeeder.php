<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TradeSetting;
use App\Models\Currency;
use Illuminate\Support\Facades\DB;

class TradeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $currencies = Currency::all();
            if ($currencies->isEmpty()) {
                return;
            }

            foreach ($currencies as $currency) {
                $settings = $this->getSettingsByType($currency->type);
                $data = [
                    'currency_id'          => $currency->id,
                    'symbol'               => $currency->symbol,
                    'is_active'            => rand(0, 10) > 2,
                    'min_amount'           => $settings['min_amount'],
                    'max_amount'           => $settings['max_amount'],
                    'payout_rate'          => $settings['payout_rate'],
                    'durations'            => $this->getDurationsByType($currency->type),
                    'trading_hours'        => $this->getTradingHoursByType($currency->type),
                    'spread'               => $settings['spread'],
                    'max_trades_per_user'  => rand(5, 20),
                ];

                TradeSetting::updateOrCreate(
                    ['currency_id' => $currency->id, 'symbol' => $currency->symbol],
                    $data
                );
            }
        });
    }

    private function getSettingsByType(string $type): array
    {
        return match ($type) {
            'crypto' => [
                'min_amount'  => 10,
                'max_amount'  => 5000,
                'payout_rate' => rand(80, 90),
                'spread'      => rand(5, 15) / 100000,
            ],
            default => [
                'min_amount'  => 1,
                'max_amount'  => 1000,
                'payout_rate' => 80,
                'spread'      => 0.00001,
            ]
        };
    }

    private function getDurationsByType(string $type): array
    {
        return match ($type) {
            'crypto'     => [30, 60, 300, 900],
            default      => [60, 300, 900],
        };
    }

    private function getTradingHoursByType(string $type): array
    {
        return match ($type) {
            'crypto' => [
                'monday'    => ['enabled' => true, 'start' => '00:00', 'end' => '23:59'],
                'tuesday'   => ['enabled' => true, 'start' => '00:00', 'end' => '23:59'],
                'wednesday' => ['enabled' => true, 'start' => '00:00', 'end' => '23:59'],
                'thursday'  => ['enabled' => true, 'start' => '00:00', 'end' => '23:59'],
                'friday'    => ['enabled' => true, 'start' => '00:00', 'end' => '23:59'],
                'saturday'  => ['enabled' => true, 'start' => '00:00', 'end' => '23:59'],
                'sunday'    => ['enabled' => true, 'start' => '00:00', 'end' => '23:59'],
            ],
            default => [
                'monday'    => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'tuesday'   => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'wednesday' => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'thursday'  => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'friday'    => ['enabled' => true, 'start' => '09:00', 'end' => '17:00'],
                'saturday'  => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
                'sunday'    => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
            ]
        };
    }
}
