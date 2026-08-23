<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryLog;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CookieController extends Controller
{
    /**
     * Admin page for managing on-hand stock counts for finished_stock
     * products (cookies, and anything else tracked by a simple count
     * rather than a recipe). Distinct from the Ingredients page, which
     * tracks raw ingredient batches with expiry/FEFO — finished_stock
     * products have no batches, just Product::stock_quantity, the same
     * column TransactionController decrements on every sale.
     */
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->where('tracking_type', 'finished_stock')
            ->search($request->string('search')->toString())
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category', $request->string('category')->toString());
            })
            ->orderBy('name')
            ->get([
                'id', 'name', 'category', 'price', 'image',
                'is_available', 'stock_quantity',
            ]);

        return Inertia::render('Inventory/Cookies', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    /**
     * Adjust the on-hand stock count for a finished_stock product.
     * Positive delta = restock (a new tray came out of the oven), negative
     * = correction, spoilage, or breakage. Never lets stock go below 0 —
     * that would silently desync from what's physically on the shelf.
     *
     * Every adjustment is logged to inventory_logs so it shows up in the
     * Inventory Report's restock history, same as ingredient batches.
     * type is 'restock' for a positive delta, 'adjustment' for a negative
     * one — quantity_change keeps the actual signed delta either way, so
     * the log always reflects exactly what changed and why.
     */
    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        if ($product->tracking_type !== 'finished_stock') {
            return back()->with('error', 'This product is not tracked by stock count.');
        }

        $validated = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $newQuantity = $product->stock_quantity + $validated['delta'];

        if ($newQuantity < 0) {
            return back()->with('error', "That would take stock below 0 (currently {$product->stock_quantity}).");
        }

        DB::transaction(function () use ($product, $validated, $newQuantity) {
            $product->update(['stock_quantity' => $newQuantity]);

            InventoryLog::create([
                'product_id' => $product->id,
                'type' => $validated['delta'] > 0 ? 'restock' : 'adjustment',
                'quantity_change' => $validated['delta'],
                'note' => $validated['note'] ?? null,
            ]);
        });

        return back()->with('success', 'Stock updated successfully.');
    }
}