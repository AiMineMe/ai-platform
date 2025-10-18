<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $plans = [
            [
                'name' => 'Basic',
                'price' => 9.99,
                'mining_fee_percentage' => 5.0,
                'mining_rate_multiplier' => 1.5,
                'features' => ['5% mining fee', '1.5x mining rate', 'Email support'],
                'is_active' => true
            ],
            [
                'name' => 'Premium',
                'price' => 19.99,
                'mining_fee_percentage' => 2.0,
                'mining_rate_multiplier' => 2.0,
                'features' => ['2% mining fee', '2x mining rate', 'Priority support', 'Advanced features'],
                'is_active' => true
            ],
            [
                'name' => 'Pro',
                'price' => 49.99,
                'mining_fee_percentage' => 0.0,
                'mining_rate_multiplier' => 3.0,
                'features' => ['No mining fees', '3x mining rate', 'VIP support', 'All features', 'Custom limits'],
                'is_active' => true
            ]
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
