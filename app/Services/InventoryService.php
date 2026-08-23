<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\InventoryLog;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /** Multiplier to convert a unit into its family's base unit (g, ml, or pcs). */
    private const TO_BASE = [
        'g' => 1, 'kg' => 1000,
        'ml' => 1, 'l' => 1000,
        'pcs' => 1,
    ];

    private const BASE_UNIT = [
        'g' => 'g', 'kg' => 'g',
        'ml' => 'ml', 'l' => 'ml',
        'pcs' => 'pcs',
    ];

    public function toBaseUnit(float $quantity, string $unit): float
    {
        $unit = strtolower($unit);

        if (! isset(self::TO_BASE[$unit])) {
            throw new \InvalidArgumentException("Unknown unit: {$unit}");
        }

        return $quantity * self::TO_BASE[$unit];
    }

    /**
     * Inverse of toBaseUnit() — converts a base-unit quantity (g/ml/pcs)
     * into the given display unit, e.g. fromBaseUnit(9990, 'kg') = 9.99.
     * Needed because batches always store quantity in base units, but
     * ingredients display stock in their own configured unit.
     */
    public function fromBaseUnit(float $baseQuantity, string $unit): float
    {
        $unit = strtolower($unit);

        if (! isset(self::TO_BASE[$unit])) {
            throw new \InvalidArgumentException("Unknown unit: {$unit}");
        }

        return $baseQuantity / self::TO_BASE[$unit];
    }

    public function baseUnitFor(string $unit): string
    {
        $unit = strtolower($unit);

        return self::BASE_UNIT[$unit] ?? throw new \InvalidArgumentException("Unknown unit: {$unit}");
    }

    /** True if $unit and $baseUnit belong to the same family (mass/volume/count). */
    public function unitsAreCompatible(string $unit, string $ingredientBaseUnit): bool
    {
        $unit = strtolower($unit);

        return isset(self::BASE_UNIT[$unit]) && self::BASE_UNIT[$unit] === $ingredientBaseUnit;
    }

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $saleItems
     * @return array<int, float> ingredient_id => required quantity in base unit
     */
    public function calculateRequiredIngredients(array $saleItems): array
    {
        $required = [];

        $productIds = collect($saleItems)->pluck('product_id');
        $products = Product::with('recipe')->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($saleItems as $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw new \RuntimeException("Product #{$item['product_id']} not found.");
            }

            if ($product->tracking_type === 'finished_stock') {
                continue;
            }

            foreach ($product->recipe as $recipeItem) {
                $baseQty = $this->toBaseUnit((float) $recipeItem->quantity, $recipeItem->unit) * $item['quantity'];
                $required[$recipeItem->ingredient_id] = ($required[$recipeItem->ingredient_id] ?? 0) + $baseQty;
            }
        }

        return $required;
    }

    /**
     * Validates ALL required stock before anything is deducted (spec §10).
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $saleItems
     * @throws InsufficientStockException
     */
    public function validateStockForSale(array $saleItems): void
    {
        $shortages = [];

        $required = $this->calculateRequiredIngredients($saleItems);

        if (! empty($required)) {
            $ingredients = Ingredient::with('validBatches')->whereIn('id', array_keys($required))->get()->keyBy('id');

            foreach ($required as $ingredientId => $neededBase) {
                $ingredient = $ingredients->get($ingredientId);
                $availableBase = (float) $ingredient->validBatches->sum('remaining_quantity');

                if ($availableBase < $neededBase) {
                    $shortages[] = [
                        'ingredient' => $ingredient->name,
                        'required' => round($neededBase, 2),
                        'available' => round($availableBase, 2),
                        'unit' => $this->baseUnitFor($ingredient->unit),
                    ];
                }
            }
        }

        $productIds = collect($saleItems)->pluck('product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($saleItems as $item) {
            $product = $products->get($item['product_id']);

            if ($product && $product->tracking_type === 'finished_stock') {
                if ((int) $product->stock_quantity < (int) $item['quantity']) {
                    $shortages[] = [
                        'ingredient' => $product->name,
                        'required' => $item['quantity'],
                        'available' => (int) $product->stock_quantity,
                        'unit' => 'pcs',
                    ];
                }
            }
        }

        if (! empty($shortages)) {
            throw new InsufficientStockException($shortages);
        }
    }

    /**
     * Validates, then deducts, inside one DB transaction (spec §9/§18).
     * Call this from your checkout/sale controller after the sale record is created.
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $saleItems
     */
    public function deductForSale(array $saleItems): void
    {
        DB::transaction(function () use ($saleItems) {
            $this->validateStockForSale($saleItems);

            $required = $this->calculateRequiredIngredients($saleItems);

            foreach ($required as $ingredientId => $neededBase) {
                $this->deductFromBatches($ingredientId, $neededBase);
            }

            $productIds = collect($saleItems)->pluck('product_id');
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($saleItems as $item) {
                $product = $products->get($item['product_id']);

                if ($product && $product->tracking_type === 'finished_stock') {
                    $product->decrement('stock_quantity', $item['quantity']);

                    InventoryLog::create([
                        'product_id' => $product->id,
                        'type' => 'sale',
                        'quantity_change' => -$item['quantity'],
                        'note' => "Sold {$item['quantity']} x {$product->name}",
                    ]);
                }
            }
        });
    }

    /** FIFO by earliest expiry (spec §9). */
    private function deductFromBatches(int $ingredientId, float $neededBase): void
    {
        $batches = IngredientBatch::where('ingredient_id', $ingredientId)
            ->where('remaining_quantity', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString());
            })
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->lockForUpdate()
            ->get();

        $remaining = $neededBase;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $consume = min((float) $batch->remaining_quantity, $remaining);
            $batch->decrement('remaining_quantity', $consume);
            $remaining -= $consume;

            InventoryLog::create([
                'ingredient_id' => $ingredientId,
                'ingredient_batch_id' => $batch->id,
                'type' => 'sale',
                'quantity_change' => -$consume,
                'note' => "Consumed from batch #{$batch->id}",
            ]);
        }

        if ($remaining > 0.0001) {
            // Guarded already by validateStockForSale — this should never trigger.
            throw new \RuntimeException('Stock inconsistency detected during deduction.');
        }
    }

    public function addBatch(Ingredient $ingredient, float $quantity, string $unit, ?string $receivedDate, ?string $expiryDate): IngredientBatch
    {
        if (! $this->unitsAreCompatible($unit, $ingredient->unit === 'pcs' ? 'pcs' : $this->baseUnitFor($ingredient->unit))) {
            throw new \InvalidArgumentException('Batch unit is not compatible with this ingredient.');
        }

        $baseQty = $this->toBaseUnit($quantity, $unit);

        $batch = $ingredient->batches()->create([
            'quantity' => $baseQty,
            'remaining_quantity' => $baseQty,
            'unit' => $this->baseUnitFor($ingredient->unit),
            'received_date' => $receivedDate ?? now()->toDateString(),
            'expiry_date' => $expiryDate,
        ]);

        InventoryLog::create([
            'ingredient_id' => $ingredient->id,
            'ingredient_batch_id' => $batch->id,
            'type' => 'restock',
            'quantity_change' => $baseQty,
            'note' => "Restocked {$quantity}{$unit}",
        ]);

        return $batch;
    }
}