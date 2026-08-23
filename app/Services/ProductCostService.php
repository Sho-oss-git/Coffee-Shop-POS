<?php

namespace App\Services;

use App\Models\Product;

/**
 * Computes a product's cost from its recipe (or, for finished_stock
 * products, its manually-set cost_price). This is the ONLY place product
 * cost is calculated — the Product management page, TransactionController's
 * COGS snapshot, and all reports call into this so the formula never
 * diverges between them.
 */
class ProductCostService
{
    public function __construct(private readonly UnitConversionService $units) {}

    /**
     * Full breakdown for the Product management page: per-ingredient cost,
     * packaging, total cost, expected profit, margin.
     *
     * @return array{
     *   ingredient_lines: array<int, array{ingredient_id:int, ingredient_name:string, quantity:float, unit:string, cost:?float}>,
     *   packaging_cost: float,
     *   product_cost: ?float,
     *   has_incomplete_cost: bool,
     *   selling_price: float,
     *   expected_profit: ?float,
     *   profit_margin: ?float,
     * }
     */
    public function costBreakdown(Product $product): array
    {
        $price = (float) $product->price;
        $packagingCost = (float) $product->packaging_cost;

        if ($product->tracking_type === 'finished_stock') {
            $cost = $product->cost_price !== null ? (float) $product->cost_price : null;

            return [
                'ingredient_lines' => [],
                'packaging_cost' => round($packagingCost, 2),
                'product_cost' => $cost,
                'has_incomplete_cost' => $cost === null,
                'selling_price' => $price,
                'expected_profit' => $cost !== null ? round($price - $cost, 2) : null,
                'profit_margin' => ($cost !== null && $price > 0)
                    ? round((($price - $cost) / $price) * 100, 2)
                    : null,
            ];
        }

        $product->loadMissing('ingredients');

        $lines = [];
        $totalIngredientCost = 0.0;
        $hasIncompleteCost = false;

        foreach ($product->ingredients as $ingredient) {
            $recipeQty = (float) $ingredient->pivot->quantity;
            $recipeUnit = $ingredient->pivot->unit;
            $costPerBase = $this->units->costPerBaseUnit($ingredient);

            if ($costPerBase === null) {
                $hasIncompleteCost = true;
                $lines[] = [
                    'ingredient_id' => $ingredient->id,
                    'ingredient_name' => $ingredient->name,
                    'quantity' => $recipeQty,
                    'unit' => $recipeUnit,
                    'cost' => null, // unit_cost not set for this ingredient yet
                ];
                continue;
            }

            $requiredBase = $this->units->normalize($recipeQty, $recipeUnit);
            $lineCost = round($requiredBase * $costPerBase, 2);
            $totalIngredientCost += $lineCost;

            $lines[] = [
                'ingredient_id' => $ingredient->id,
                'ingredient_name' => $ingredient->name,
                'quantity' => $recipeQty,
                'unit' => $recipeUnit,
                'cost' => $lineCost,
            ];
        }

        $productCost = $hasIncompleteCost ? null : round($totalIngredientCost + $packagingCost, 2);

        return [
            'ingredient_lines' => $lines,
            'packaging_cost' => round($packagingCost, 2),
            'product_cost' => $productCost,
            'has_incomplete_cost' => $hasIncompleteCost,
            'selling_price' => $price,
            'expected_profit' => $productCost !== null ? round($price - $productCost, 2) : null,
            'profit_margin' => ($productCost !== null && $price > 0)
                ? round((($price - $productCost) / $price) * 100, 2)
                : null,
        ];
    }

    /** Single number used to snapshot COGS at the moment of sale. */
    public function currentCost(Product $product): ?float
    {
        return $this->costBreakdown($product)['product_cost'];
    }
}