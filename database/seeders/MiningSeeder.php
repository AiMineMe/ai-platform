<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MiningSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $achievements = [
            [
                'name' => 'First Mine',
                'description' => 'Complete your first mining session',
                'icon' => 'fas fa-gem',
                'type' => 'mining',
                'condition' => 'sessions_completed >= 1',
                'reward_amount' => 10.0,
                'reward_type' => 'tokens',
                'required_value' => 1,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Five Day Streak',
                'description' => 'Mine for 5 consecutive days',
                'icon' => 'fas fa-fire',
                'type' => 'streak',
                'condition' => 'streak_days >= 5',
                'reward_amount' => 20.0,
                'reward_type' => 'tokens',
                'required_value' => 5,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Level 10 Miner',
                'description' => 'Reach mining level 10',
                'icon' => 'fas fa-level-up-alt',
                'type' => 'level',
                'condition' => 'level >= 10',
                'reward_amount' => 50.0,
                'reward_type' => 'tokens',
                'required_value' => 10,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Big Miner',
                'description' => 'Mine a total of 1000 tokens',
                'icon' => 'fas fa-coins',
                'type' => 'total',
                'condition' => 'total_mined >= 1000',
                'reward_amount' => 100.0,
                'reward_type' => 'tokens',
                'required_value' => 1000,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        DB::table('mining_achievements')->insert($achievements);
        $competitions = [
            [
                'name' => 'Daily Mining Contest',
                'description' => 'Compete to mine the most tokens today',
                'type' => 'daily',
                'prize_pool' => 500.00,
                'entry_fee' => 5.00,
                'admin_fee_percentage' => 20.00,
                'prizes' => json_encode([
                    ['position' => 1, 'reward' => 300],
                    ['position' => 2, 'reward' => 150],
                    ['position' => 3, 'reward' => 50],
                ]),
                'starts_at' => Carbon::now()->startOfDay(),
                'ends_at' => Carbon::now()->endOfDay(),
                'is_active' => true,
                'max_participants' => 100,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Weekly Mining Challenge',
                'description' => 'Top miners compete over the week',
                'type' => 'weekly',
                'prize_pool' => 3500.00,
                'entry_fee' => 25.00,
                'admin_fee_percentage' => 30.00,
                'prizes' => json_encode([
                    ['position' => 1, 'reward' => 2000],
                    ['position' => 2, 'reward' => 1000],
                    ['position' => 3, 'reward' => 500],
                ]),
                'starts_at' => Carbon::now()->startOfWeek(),
                'ends_at' => Carbon::now()->endOfWeek(),
                'is_active' => true,
                'max_participants' => 500,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Monthly Mining Championship',
                'description' => 'The ultimate monthly mining competition with huge prizes',
                'type' => 'monthly',
                'prize_pool' => 15000.00,
                'entry_fee' => 100.00,
                'admin_fee_percentage' => 25.00,
                'prizes' => json_encode([
                    ['position' => 1, 'reward' => 8000],
                    ['position' => 2, 'reward' => 4000],
                    ['position' => 3, 'reward' => 2000],
                    ['position' => 4, 'reward' => 1000],
                ]),
                'starts_at' => Carbon::now()->startOfMonth(),
                'ends_at' => Carbon::now()->endOfMonth(),
                'is_active' => true,
                'max_participants' => 1000,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Beginner Mining Tournament',
                'description' => 'Perfect for new miners to get started',
                'type' => 'daily',
                'prize_pool' => 100.00,
                'entry_fee' => 1.00,
                'admin_fee_percentage' => 15.00,
                'prizes' => json_encode([
                    ['position' => 1, 'reward' => 60],
                    ['position' => 2, 'reward' => 25],
                    ['position' => 3, 'reward' => 15],
                ]),
                'starts_at' => Carbon::now()->addDay()->startOfDay(),
                'ends_at' => Carbon::now()->addDay()->endOfDay(),
                'is_active' => true,
                'max_participants' => 50,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Pro Miners Only',
                'description' => 'High-stakes competition for experienced miners',
                'type' => 'weekly',
                'prize_pool' => 10000.00,
                'entry_fee' => 250.00,
                'admin_fee_percentage' => 35.00,
                'prizes' => json_encode([
                    ['position' => 1, 'reward' => 6000],
                    ['position' => 2, 'reward' => 2500],
                    ['position' => 3, 'reward' => 1000],
                    ['position' => 4, 'reward' => 500],
                ]),
                'starts_at' => Carbon::now()->addWeek()->startOfWeek(),
                'ends_at' => Carbon::now()->addWeek()->endOfWeek(),
                'is_active' => false,
                'max_participants' => 200,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        DB::table('mining_competitions')->insert($competitions);
    }
}
