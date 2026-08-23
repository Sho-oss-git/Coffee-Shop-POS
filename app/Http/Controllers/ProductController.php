<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\ProductCostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ProductCostService $costs) {}

    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with('recipe.ingredient')
            ->search($request->string('search')->toString())
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category', $request->string('category')->toString());
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('is_available', $request->string('status')->toString() === 'available');
            })
            ->orderBy('name')
            ->get()
            // Cost breakdown is admin-only context, computed fresh from the
            // current recipe/ingredient costs — NOT the historical per-sale
            // snapshot used in reports. Attached here so the Product
            // management page can show "current" cost/profit/margin without
            // a second round trip.
            ->map(function (Product $product) {
                $product->setAttribute('cost_breakdown', $this->costs->costBreakdown($product));

                return $product;
            });

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'ingredients' => Ingredient::query()->orderBy('name')->get(['id', 'name', 'unit']),
            'filters' => $request->only(['search', 'category', 'status']),
        ]);
    }

    /**
     * Read-only product list for the cashier POS view.
     * Only exposes what a cashier needs to see: name, category, price, image, availability.
     * Never exposes cost/ingredient/recipe data.
     */
    public function cashierIndex(Request $request): Response
    {
        $products = Product::query()
            ->select(['id', 'name', 'category', 'price', 'image', 'is_available', 'tracking_type', 'stock_quantity'])
            // Loaded only to compute availability below — never included in the
            // mapped response, so recipe/ingredient data still isn't exposed
            // to the cashier.
            ->with('recipe.ingredient')
            ->search($request->string('search')->toString())
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category', $request->string('category')->toString());
            })
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category,
                    'price' => $product->price,
                    'image_url' => $product->image ? Storage::url($product->image) : null,
                    'is_available' => $product->isAvailable(),
                    'stock_left' => $product->tracking_type === 'finished_stock'
                        ? $product->stock_quantity
                        : null,
                ];
            });

        return Inertia::render('cashier/Products/Index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'category']),
            // Preview of the next daily sequential order number, purely for
            // display in the Order Options modal before checkout. The
            // AUTHORITATIVE number is (re)computed atomically in
            // TransactionController@store — this is just what the cashier
            // sees ahead of time, and refreshes automatically after each
            // completed sale since that request triggers a full page visit.
            'next_order_number' => Transaction::whereDate('created_at', now()->toDateString())->count() + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        DB::transaction(function () use ($validated) {
            $product = Product::create(collect($validated)->except('ingredients')->all());
            $this->syncRecipe($product, $validated['ingredients'] ?? []);
        });

        return back()->with('success', 'Product added successfully.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request, $product);

        if ($request->user()?->isManager() && (float) $validated['price'] !== (float) $product->price) {
            abort(403, 'Major price changes require Admin approval through Action Requests.');
        }

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        DB::transaction(function () use ($product, $validated) {
            $product->update(collect($validated)->except('ingredients')->all());
            $this->syncRecipe($product, $validated['ingredients'] ?? []);
        });

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete(); // product_ingredients rows cascade via FK

        return back()->with('success', 'Product deleted successfully.');
    }

    private function syncRecipe(Product $product, array $ingredients): void
    {
        $product->recipe()->delete();

        foreach ($ingredients as $item) {
            $product->recipe()->create([
                'ingredient_id' => $item['ingredient_id'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
            ]);
        }
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($product?->id)],
            'category' => ['required', 'string', 'max:255', Rule::exists('categories', 'name')],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:4096'],
            'is_available' => ['required', 'boolean'],
            'tracking_type' => ['required', Rule::in(['recipe', 'finished_stock'])],
            'stock_quantity' => ['nullable', 'required_if:tracking_type,finished_stock', 'integer', 'min:0'],
            'packaging_cost' => ['nullable', 'numeric', 'min:0'],
            // Only meaningful for finished_stock — there's no recipe to
            // derive a cost from, so it must be entered manually.
            'cost_price' => ['nullable', 'required_if:tracking_type,finished_stock', 'numeric', 'min:0'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.ingredient_id' => ['required_with:ingredients', 'exists:ingredients,id'],
            'ingredients.*.quantity' => ['required_with:ingredients', 'numeric', 'min:0.01'],
            'ingredients.*.unit' => ['required_with:ingredients', Rule::in(['g', 'kg', 'ml', 'l', 'pcs'])],
        ]);

        $this->assertNoDuplicateIngredients($request);
        $this->assertUnitsAreCompatible($request);

        $validated['is_available'] = filter_var($validated['is_available'], FILTER_VALIDATE_BOOLEAN);

        if ($validated['tracking_type'] !== 'finished_stock') {
            $validated['stock_quantity'] = null;
            $validated['cost_price'] = null; // recipe-tracked products cost is derived, never manual
        } else {
            $validated['ingredients'] = []; // finished_stock products don't consume a recipe
        }

        return $validated;
    }

    private function assertNoDuplicateIngredients(Request $request): void
    {
        $ids = collect($request->input('ingredients', []))->pluck('ingredient_id');

        if ($ids->count() !== $ids->unique()->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ingredients' => 'Each ingredient can only appear once in a recipe.',
            ]);
        }
    }

    private function assertUnitsAreCompatible(Request $request): void
    {
        $items = $request->input('ingredients', []);

        if (empty($items)) {
            return;
        }

        $ingredients = Ingredient::whereIn('id', collect($items)->pluck('ingredient_id'))->get()->keyBy('id');
        $service = app(\App\Services\InventoryService::class);

        foreach ($items as $index => $item) {
            $ingredient = $ingredients->get($item['ingredient_id'] ?? null);

            if (! $ingredient) {
                continue;
            }

            $ingredientBase = $ingredient->unit === 'pcs' ? 'pcs' : $service->baseUnitFor($ingredient->unit);

            if (! $service->unitsAreCompatible($item['unit'], $ingredientBase)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "ingredients.{$index}.unit" => "Unit is not compatible with {$ingredient->name} ({$ingredient->unit}).",
                ]);
            }
        }
    }
}