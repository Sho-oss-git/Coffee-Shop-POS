<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class CookieSeeder extends Seeder
{
    public function run(): void
    {
        $cookies = [
            [
                'name' => 'Chocolate Chip Cookie',
                'price' => 35.00,
                'image' => 'products/cQQ6nw4mnsWDlYFAWDrAs9jJLn744yF4GTEKP1LF.jpg',
                'packaging_cost' => 2.00,
                'cost_price' => 12.00,
            ],
            [
                'name' => 'Double Chocolate Cookie',
                'price' => 40.00,
                'image' => 'products/FClkMxLhTxe2uvynwjfXr4lrKRpJcqQuUvgCuqI0.jpg',
                'packaging_cost' => 2.00,
                'cost_price' => 15.00,
            ],
            [
                'name' => 'Butter Cookie',
                'price' => 30.00,
                'image' => 'products/GOskff98JqluXvIBHtbAd8WvLGTeAv95byYNELa4.jpg',
                'packaging_cost' => 2.00,
                'cost_price' => 10.00,
            ],
            [
                'name' => 'Vanilla Sugar Cookie',
                'price' => 32.00,
                'image' => 'products/VD4NovgGodJbmmGHQtTdIgd88xJxzFV5KIVmObLN.jpg',
                'packaging_cost' => 2.00,
                'cost_price' => 11.00,
            ],
        ];

        foreach ($cookies as $data) {
            Product::updateOrCreate(
                ['name' => $data['name']],
                [
                    'category' => 'cookies',
                    'tracking_type' => 'recipe',
                    'is_available' => true,
                    'stock_quantity' => null,
                ] + $data
            );
        }
    }
}
