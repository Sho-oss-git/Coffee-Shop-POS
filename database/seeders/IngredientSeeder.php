<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\IngredientBatch;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $ingredients = [
            ['name' => 'All-Purpose Flour', 'measurement_type' => 'weight', 'unit' => 'kg', 'minimum_stock' => 5, 'unit_cost' => 45.00],
            ['name' => 'Granulated Sugar',   'measurement_type' => 'weight', 'unit' => 'kg', 'minimum_stock' => 3, 'unit_cost' => 50.00],
            ['name' => 'Butter',             'measurement_type' => 'weight', 'unit' => 'kg', 'minimum_stock' => 2, 'unit_cost' => 250.00],
            ['name' => 'Eggs',               'measurement_type' => 'piece', 'unit' => 'pcs', 'minimum_stock' => 12, 'unit_cost' => 9.00],
            ['name' => 'Milk',               'measurement_type' => 'volume', 'unit' => 'L', 'minimum_stock' => 4, 'unit_cost' => 90.00],
            ['name' => 'Chocolate Chips',    'measurement_type' => 'weight', 'unit' => 'kg', 'minimum_stock' => 1, 'unit_cost' => 320.00],
            ['name' => 'Vanilla Extract',    'measurement_type' => 'volume', 'unit' => 'ml', 'minimum_stock' => 250, 'unit_cost' => 0.80],
            ['name' => 'Baking Powder',      'measurement_type' => 'weight', 'unit' => 'g', 'minimum_stock' => 200, 'unit_cost' => 0.20],
            ['name' => 'Salt',               'measurement_type' => 'weight', 'unit' => 'g', 'minimum_stock' => 500, 'unit_cost' => 0.05],
            ['name' => 'Cocoa Powder',       'measurement_type' => 'weight', 'unit' => 'kg', 'minimum_stock' => 1, 'unit_cost' => 400.00],
            ['name' => 'Coffee Beans',       'measurement_type' => 'weight', 'unit' => 'kg', 'minimum_stock' => 2, 'unit_cost' => 600.00],
        ];

        foreach ($ingredients as $data) {
            $ingredient = Ingredient::updateOrCreate(
                ['name' => $data['name']],
                $data
            );

            // Give each ingredient an in-stock batch so product availability works.
            $batchQty = max($data['minimum_stock'] * 2, 1);
            IngredientBatch::updateOrCreate(
                ['ingredient_id' => $ingredient->id, 'received_date' => now()->toDateString()],
                [
                    'unit' => $data['unit'],
                    'quantity' => $batchQty,
                    'remaining_quantity' => $batchQty,
                    'received_date' => now()->toDateString(),
                    'expiry_date' => now()->addMonths(6)->toDateString(),
                    'total_cost' => (int) round($batchQty * $data['unit_cost']),
                ]
            );
        }
    }
}
