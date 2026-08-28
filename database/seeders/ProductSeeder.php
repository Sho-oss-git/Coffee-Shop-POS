<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Espresso',
                'category' => 'coffee',
                'price' => 80.00,
                'image' => 'products/07kOPHB2irgVBlZgQnDQmQy4TetbdGoZ5phdZ2gn.jpg',
                'tracking_type' => 'recipe',
                'is_available' => true,
                'packaging_cost' => 5.00,
                'cost_price' => 25.00,
            ],
            [
                'name' => 'Cappuccino',
                'category' => 'coffee',
                'price' => 120.00,
                'image' => 'products/2gQ38rRkb0ajzhBpR8AUYs7nOlReNQEndeZ7AWIf.jpg',
                'tracking_type' => 'recipe',
                'is_available' => true,
                'packaging_cost' => 8.00,
                'cost_price' => 35.00,
            ],
            [
                'name' => 'Latte',
                'category' => 'coffee',
                'price' => 130.00,
                'image' => 'products/9belMeC6swmlAhhS0TqoqlzmhtnbYSQn4KK50gAY.jpg',
                'tracking_type' => 'recipe',
                'is_available' => true,
                'packaging_cost' => 8.00,
                'cost_price' => 40.00,
            ],
            [
                'name' => 'Hot Chocolate',
                'category' => 'drinks',
                'price' => 110.00,
                'image' => 'products/9j18siC8Y9z8AWisz7rq07QwkmoHmaoQixglC5X4.jpg',
                'tracking_type' => 'recipe',
                'is_available' => true,
                'packaging_cost' => 7.00,
                'cost_price' => 30.00,
            ],
        ];

        foreach ($products as $data) {
            Product::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}
