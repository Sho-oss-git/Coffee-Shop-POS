<?php

namespace App\Http\Controllers;

/**
 * ============================================================================
 * REFERENCE ONLY — NOT A DROP-IN FILE
 * ============================================================================
 * You didn't include your actual TransactionController / POS sale controller
 * in the source you shared, so I couldn't safely edit it in place. This shows
 * the exact integration pattern to merge into whatever currently:
 *   - validates the sale
 *   - creates the Sale/Transaction + line item rows
 *   - was previously doing its own (or no) inventory deduction
 *
 * Everything ingredient/unit-related routes through
 * IngredientConsumptionService::consumeForSale(), which itself wraps
 * DB::transaction() — so if you already open your own transaction around
 * the whole sale (recommended, so the Sale row and the stock deduction
 * commit/rollback together), just call consumeForSale() *inside* that
 * transaction rather than nesting a second one.
 * ============================================================================
 */

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
// use App\Models\Sale; // your actual model
use App\Services\IngredientConsumptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class POSController extends Controller
{
    public function __construct(private readonly IngredientConsumptionService $consumption) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $products = Product::with('ingredients')
            ->whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        // STEP 1-3: build sale lines (product + quantity sold).
        $lines = collect($validated['items'])->map(fn ($item) => [
            'product' => $products[$item['product_id']],
            'quantity' => $item['quantity'],
        ])->all();

        try {
            DB::transaction(function () use ($lines, $validated) {
                // STEPS 4-9: convert units, check ALL stock, deduct via FEFO.
                // Throws InsufficientStockException and rolls back everything
                // (including anything below) if any ingredient is short.
                $this->consumption->consumeForSale($lines);

                // STEP 10-11: save your actual sale + line items + movement
                // logs here, inside the same transaction, e.g.:
                //
                // $sale = Sale::create([...]);
                // foreach ($validated['items'] as $item) {
                //     $sale->items()->create([...]);
                // }
            });
            // STEP 12: COMMIT happens automatically when the closure above
            // returns without throwing.
        } catch (InsufficientStockException $e) {
            // ROLLBACK already happened inside consumeForSale()'s own
            // DB::transaction — nothing was deducted, nothing was saved.
            return back()->with('error', $e->getMessage())->withErrors([
                'items' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Sale completed.');
    }
}