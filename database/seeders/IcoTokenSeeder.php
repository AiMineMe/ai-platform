<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IcoToken;
use Carbon\Carbon;

class IcoTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tokens = [
            [
                'name'           => 'MineInvest Token',
                'symbol'         => 'CEX',
                'description'    => 'The native utility token for the MineInvest platform, providing trading fee discounts and governance rights.',
                'price'          => 0.50,
                'current_price'  => 0.50,
                'total_supply'   => 1_000_000,
                'tokens_sold'    => 650_000,
                'sale_start_date'=> Carbon::now()->subDays(30),
                'sale_end_date'  => Carbon::now()->addDays(60),
                'status'         => 'active',
                'is_featured'    => true,
            ],
            [
                'name'           => 'DeFi Pro',
                'symbol'         => 'DPRO',
                'description'    => 'Decentralized finance protocol token enabling yield farming and liquidity provision.',
                'price'          => 1.25,
                'current_price'  => 1.25,
                'total_supply'   => 500_000,
                'tokens_sold'    => 120_000,
                'sale_start_date'=> Carbon::now()->subDays(15),
                'sale_end_date'  => Carbon::now()->addDays(45),
                'status'         => 'active',
                'is_featured'    => true,
            ],
            [
                'name'           => 'GameFi Coin',
                'symbol'         => 'GFC',
                'description'    => 'Gaming ecosystem token for NFT rewards and in-game purchases.',
                'price'          => 0.15,
                'current_price'  => 0.15,
                'total_supply'   => 2_000_000,
                'tokens_sold'    => 800_000,
                'sale_start_date'=> Carbon::now()->subDays(45),
                'sale_end_date'  => Carbon::now()->addDays(15),
                'status'         => 'active',
                'is_featured'    => false,
            ],
            [
                'name'           => 'MetaVerse Token',
                'symbol'         => 'MVT',
                'description'    => 'Virtual real-estate and metaverse economy token.',
                'price'          => 2.00,
                'current_price'  => 2.00,
                'total_supply'   => 300_000,
                'tokens_sold'    => 300_000,
                'sale_start_date'=> Carbon::now()->subDays(90),
                'sale_end_date'  => Carbon::now()->subDays(10),
                'status'         => 'completed',
                'is_featured'    => false,
            ],
            [
                'name'           => 'Green Energy Coin',
                'symbol'         => 'GEC',
                'description'    => 'Sustainable energy project token supporting renewable energy initiatives.',
                'price'          => 0.75,
                'current_price'  => 0.75,
                'total_supply'   => 800_000,
                'tokens_sold'    => 200_000,
                'sale_start_date'=> Carbon::now()->subDays(20),
                'sale_end_date'  => Carbon::now()->addDays(40),
                'status'         => 'paused',
                'is_featured'    => false,
            ],
            [
                'name'           => 'AI Analytics',
                'symbol'         => 'AIA',
                'description'    => 'Artificial-intelligence and data-analytics platform token.',
                'price'          => 3.50,
                'current_price'  => 3.50,
                'total_supply'   => 200_000,
                'tokens_sold'    => 0,
                'sale_start_date'=> Carbon::now()->addDays(10),
                'sale_end_date'  => Carbon::now()->addDays(70),
                'status'         => 'active',
                'is_featured'    => false,
            ],
            [
                'name'           => 'Supply Chain Token',
                'symbol'         => 'SCT',
                'description'    => 'Blockchain-based supply-chain management and tracking solution.',
                'price'          => 0.90,
                'current_price'  => 0.90,
                'total_supply'   => 600_000,
                'tokens_sold'    => 50_000,
                'sale_start_date'=> Carbon::now()->subDays(60),
                'sale_end_date'  => Carbon::now()->subDays(30),
                'status'         => 'cancelled',
                'is_featured'    => false,
            ],
            [
                'name'           => 'Social Media Coin',
                'symbol'         => 'SMC',
                'description'    => 'Decentralized social-media platform with content-creator rewards.',
                'price'          => 0.25,
                'current_price'  => 0.25,
                'total_supply'   => 1_500_000,
                'tokens_sold'    => 900_000,
                'sale_start_date'=> Carbon::now()->subDays(25),
                'sale_end_date'  => Carbon::now()->addDays(35),
                'status'         => 'active',
                'is_featured'    => true,
            ],
        ];

        foreach ($tokens as $tokenData) {
            IcoToken::create($tokenData);
        }
    }
}
