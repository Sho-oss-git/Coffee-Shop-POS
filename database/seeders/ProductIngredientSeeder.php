<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductIngredient;
use Illuminate\Database\Seeder;

class ProductIngredientSeeder extends Seeder
{
    public function run(): void
    {
        // Lookup helper by ingredient name.
        $ing = fn (string $name) => Ingredient::where('name', $name)->firstOrFail();

        // Recipes: [product name, [ingredient => [quantity, unit]]]
        $recipes = [
            'Espresso' => [
                'Coffee Beans' => [18, 'g'],
            ],
            'Cappuccino' => [
                'Coffee Beans' => [18, 'g'],
                'Milk' => [150, 'ml'],
            ],
            'Latte' => [
                'Coffee Beans' => [18, 'g'],
                'Milk' => [250, 'ml'],
            ],
            'Hot Chocolate' => [
                'Milk' => [250, 'ml'],
                'Cocoa Powder' => [20, 'g'],
            ],
            'Chocolate Chip Cookie' => [
                'All-Purpose Flour' => [120, 'g'],
                'Granulated Sugar' => [60, 'g'],
                'Butter' => [80, 'g'],
                'Eggs' => [1, 'pcs'],
                'Chocolate Chips' => [50, 'g'],
                'Baking Powder' => [2, 'g'],
                'Salt' => [1, 'g'],
                'Vanilla Extract' => [5, 'ml'],
            ],
            'Double Chocolate Cookie' => [
                'All-Purpose Flour' => [110, 'g'],
                'Granulated Sugar' => [60, 'g'],
                'Butter' => [80, 'g'],
                'Eggs' => [1, 'pcs'],
                'Chocolate Chips' => [80, 'g'],
                'Cocoa Powder' => [15, 'g'],
                'Baking Powder' => [2, 'g'],
                'Salt' => [1, 'g'],
                'Vanilla Extract' => [5, 'ml'],
            ],
            'Butter Cookie' => [
                'All-Purpose Flour' => [130, 'g'],
                'Granulated Sugar' => [50, 'g'],
                'Butter' => [100, 'g'],
                'Eggs' => [1, 'pcs'],
                'Salt' => [1, 'g'],
                'Vanilla Extract' => [5, 'ml'],
            ],
            'Vanilla Sugar Cookie' => [
                'All-Purpose Flour' => [120, 'g'],
                'Granulated Sugar' => [70, 'g'],
                'Butter' => [90, 'g'],
                'Eggs' => [1, 'pcs'],
                'Baking Powder' => [2, 'g'],
                'Salt' => [1, 'g'],
                'Vanilla Extract' => [8, 'ml'],
            ],
        ];

        foreach ($recipes as $productName => $lines) {
            $product = Product::where('name', $productName)->firstOrFail();

            foreach ($lines as $ingredientName => [$quantity, $unit]) {
                ProductIngredient::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'ingredient_id' => $ing($ingredientName)->id,
                    ],
                    [
                        'quantity' => $quantity,
                        'unit' => $unit,
                    ]
                );
            }
        }
    }
}
