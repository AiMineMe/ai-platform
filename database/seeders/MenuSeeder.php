<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            [
                'identifier' => 'services',
                'menu_name' => 'service',
                'path' => 'services',
                'components' => ['Service', 'Network'],
                'component_props' => null,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'identifier' => 'crypto-prices',
                'menu_name' => 'crypto Prices',
                'path' => 'crypto-prices',
                'components' => ['CryptoPrice'],
                'component_props' => null,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'identifier' => 'mining',
                'menu_name' => 'mining',
                'path' => 'mining',
                'components' => ['Mining'],
                'component_props' => null,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'identifier' => 'features',
                'menu_name' => 'features',
                'path' => 'features',
                'components' => ['AdvancedFeature', 'CryptoPrice'],
                'component_props' => null,
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($menus as $menu) {
            Menu::updateOrCreate(
                ['identifier' => $menu['identifier']],
                $menu
            );
        }
    }
}
