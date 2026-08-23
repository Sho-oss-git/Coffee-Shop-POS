<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Centralized ingredient consumption for the JC66 POS.
 *
 * This is the ONLY place inventory is deducted for a sale. POS.vue / Product.vue /
 * Ingredient.vue may preview these numbers, but they are never authoritative —
 * this service (backend) always recalculates and re-validates.
 */
class IngredientConsumptionService
{
    public function __construct(private readonly UnitConversionService $units) {}

    /**
     * Recipe requirement for ONE product's recipe, scaled by quantity sold,
     * expressed in the canonical base unit of each ingredient's measurement type.
     *
     * @return array<int, array{ingredient: Ingredient, required_base: float, measurement_type: string, base_unit: string}>
     *         keyed by ingredient_id
     */
    public function calculateRequirements(Product $product, float $quantitySold): array
    {
        $requirements = [];

        // Assumes Product::ingredients() is a belongsToMany through product_ingredients
        // with pivot columns `quantity` (recipe amount for ONE product) and `unit`
        // (the recipe consumption unit — independent of the ingredient's display unit).
        foreach ($product->ingredients as $ingredient) {
            $recipeQtyPerUnit = (float) $ingredient->pivot->quantity;
            $recipeUnit = $ingredient->pivot->unit;

            $measurementType = $this->units->getMeasurementType($recipeUnit);
            $requiredInRecipeUnit = $recipeQtyPerUnit * $quantitySold;
            $requiredBase = $this->units->normalize($requiredInRecipeUnit, $recipeUnit);

            if (! isset($requirements[$ingredient->id])) {
                $requirements[$ingredient->id] = [
                    'ingredient' => $ingredient,
                    'required_base' => 0.0,
                    'measurement_type' => $measurementType,
                    'base_unit' => $this->units->getBaseUnit($measurementType),
                ];
            }

            $requirements[$ingredient->id]['required_base'] += $requiredBase;
        }

        return $requirements;
    }

    /**
     * Merge requirements across every line of a sale (several products, several quantities),
     * so an ingredient shared by multiple products (e.g. coffee beans in Americano AND Latte)
     * is only checked/deducted once, for its true combined total.
     *
     * @param  array<int, array{product: Product, quantity: float}>  $lines
     * @return array<int, array{ingredient: Ingredient, required_base: float, measurement_type: string, base_unit: string}>
     */
    public function calculateRequirementsForSale(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $lineRequirements = $this->calculateRequirements($line['product'], (float) $line['quantity']);
            $merged = $this->mergeRequirements($merged, $lineRequirements);
        }

        return $merged;
    }

    /**
     * Merge one more set of requirements into an existing accumulator, combining
     * required_base for any ingredient_id present in both. Used by callers (e.g.
     * TransactionController) that build up requirements line-by-line while they
     * validate each sale item, rather than passing every line in at once via
     * calculateRequirementsForSale().
     *
     * @param  array<int, array{ingredient: Ingredient, required_base: float, measurement_type: string, base_unit: string}>  $base
     * @param  array<int, array{ingredient: Ingredient, required_base: float, measurement_type: string, base_unit: string}>  $additional
     * @return array<int, array{ingredient: Ingredient, required_base: float, measurement_type: string, base_unit: string}>
     */
    public function mergeRequirements(array $base, array $additional): array
    {
        foreach ($additional as $ingredientId => $requirement) {
            if (! isset($base[$ingredientId])) {
                $base[$ingredientId] = $requirement;
            } else {
                $base[$ingredientId]['required_base'] += $requirement['required_base'];
            }
        }

        return $base;
    }

    /**
     * Check every requirement against currently available (non-expired) stock.
     * Returns an empty array if everything is available, otherwise one entry
     * per ingredient that is short — expressed back in that ingredient's
     * display unit so the error message matches what the user sees on screen.
     *
     * @return array<int, array{ingredient: string, required: float, available: float, unit: string}>
     */
    public function checkAvailability(array $requirements): array
    {
        $shortfalls = [];

        foreach ($requirements as $requirement) {
            /** @var Ingredient $ingredient */
            $ingredient = $requirement['ingredient'];
            $availableBase = $this->availableStockInBase($ingredient);

            // Small epsilon to avoid float-precision false positives at the boundary.
            if ($availableBase + 1e-9 < $requirement['required_base']) {
                $shortfalls[] = [
                    'ingredient' => $ingredient->name,
                    'required' => $this->units->convertFromBase(
                        $requirement['required_base'],
                        $requirement['measurement_type'],
                        $ingredient->unit
                    ),
                    'available' => $this->units->convertFromBase(
                        $availableBase,
                        $requirement['measurement_type'],
                        $ingredient->unit
                    ),
                    'unit' => $ingredient->unit,
                ];
            }
        }

        return $shortfalls;
    }

    /** Sum of all non-expired batch remainders for an ingredient, in canonical base units. */
    private function availableStockInBase(Ingredient $ingredient): float
    {
        return $ingredient->validBatches()
            ->get()
            ->sum(fn (IngredientBatch $batch) => $this->units->normalize(
                (float) $batch->remaining_quantity,
                $batch->unit ?? $ingredient->unit
            ));
    }

    /**
     * Deduct $requiredBase (canonical units) from an ingredient's batches, First-Expire-First-Out.
     * Expired batches are skipped entirely. Must be called inside a DB transaction with the
     * batches locked (consumeForSale does this) — never call in isolation during a live sale.
     */
    public function consumeFromBatches(Ingredient $ingredient, float $requiredBase, string $measurementType): void
    {
        $remaining = $requiredBase;

        $batches = $ingredient->batches()
            ->where('remaining_quantity', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now()->toDateString());
            })
            // FEFO: soonest expiry first; batches with no expiry date are used last.
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 1e-9) {
                break;
            }

            $batchUnit = $batch->unit ?? $ingredient->unit;
            $batchRemainingBase = $this->units->normalize((float) $batch->remaining_quantity, $batchUnit);
            $deductBase = min($batchRemainingBase, $remaining);

            $newRemainingBase = $batchRemainingBase - $deductBase;
            $batch->remaining_quantity = $this->units->convertFromBase($newRemainingBase, $measurementType, $batchUnit);
            $batch->save();

            $remaining -= $deductBase;
        }

        if ($remaining > 1e-9) {
            // checkAvailability() should have already prevented this — this is a defensive
            // guard against a race condition between the check and the locked deduction.
            throw new InsufficientStockException("Stock for {$ingredient->name} changed before the sale could complete. Please retry.");
        }
    }

    /**
     * Deduct a full requirements set (already merged/validated by the caller) via
     * FEFO. Unlike consumeForSale(), this does NOT open its own transaction or
     * re-run checkAvailability() — it assumes the caller already validated
     * availability and is already inside its own DB transaction (e.g.
     * TransactionController::store()), so it only performs the deduction step.
     *
     * @param  array<int, array{ingredient: Ingredient, required_base: float, measurement_type: string, base_unit: string}>  $requirements
     */
    public function consumeIngredients(array $requirements): void
    {
        foreach ($requirements as $requirement) {
            $this->consumeFromBatches(
                $requirement['ingredient'],
                $requirement['required_base'],
                $requirement['measurement_type']
            );
        }
    }

    /**
     * Full sale flow: BEGIN -> validate all lines -> check ALL required stock ->
     * deduct via FEFO -> COMMIT. If ANY ingredient is short, nothing is deducted
     * (no partial deduction) and InsufficientStockException is thrown.
     *
     * @param  array<int, array{product: Product, quantity: float}>  $lines
     */
    public function consumeForSale(array $lines): void
    {
        DB::transaction(function () use ($lines) {
            $requirements = $this->calculateRequirementsForSale($lines);

            $shortfalls = $this->checkAvailability($requirements);

            if (! empty($shortfalls)) {
                $message = 'Insufficient stock: '.collect($shortfalls)
                    ->map(fn ($s) => sprintf(
                        '%s (required %s%s, available %s%s)',
                        $s['ingredient'],
                        rtrim(rtrim(number_format($s['required'], 4, '.', ''), '0'), '.'),
                        $s['unit'],
                        rtrim(rtrim(number_format($s['available'], 4, '.', ''), '0'), '.'),
                        $s['unit'],
                    ))
                    ->join('; ');

                throw new InsufficientStockException($message, $shortfalls);
            }

            $this->consumeIngredients($requirements);
        });
    }
}