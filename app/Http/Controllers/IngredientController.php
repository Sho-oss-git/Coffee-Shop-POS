<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\UnitConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Exports\InventoryReportExport;
use App\Exports\InventoryReportWordExport;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly UnitConversionService $units,
    ) {}

    public function index(Request $request): Response
    {
        $ingredients = Ingredient::query()
            ->with('validBatches')
            ->search($request->string('search')->toString())
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Ingredients', [
            'ingredients' => $ingredients,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateIngredient($request);

        $ingredient = Ingredient::create([
            'name' => $validated['name'],
            'measurement_type' => $validated['measurement_type'],
            'unit' => $validated['unit'],
            'minimum_stock' => $validated['minimum_stock'],
            'unit_cost' => $validated['unit_cost'] ?? null,
        ]);

        // Optional initial stock — creates the first batch behind the scenes.
        if (! empty($validated['quantity'])) {
            $this->inventory->addBatch(
                $ingredient,
                (float) $validated['quantity'],
                $validated['unit'],
                $validated['received_date'] ?? null,
                $validated['expiry_date'] ?? null,
            );
        }

        return back()->with('success', 'Ingredient added successfully.');
    }

    public function update(
        Request $request,
        Ingredient $ingredient
    ): RedirectResponse {
        $validated = $this->validateIngredient($request, $ingredient);

        // Prevent changing units when batches already exist.
        if (
            $ingredient->batches()->exists()
            && (
                $validated['measurement_type'] !== $ingredient->measurement_type
                || $validated['unit'] !== $ingredient->unit
            )
        ) {
            return back()->with(
                'error',
                'Cannot change measurement type or unit for an ingredient that already has stock batches.'
            );
        }

        $ingredient->update([
            'name' => $validated['name'],
            'measurement_type' => $validated['measurement_type'],
            'unit' => $validated['unit'],
            'minimum_stock' => $validated['minimum_stock'],
            'unit_cost' => $validated['unit_cost'] ?? null,
        ]);

        return back()->with('success', 'Ingredient updated successfully.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        if ($ingredient->products()->exists()) {
            return back()->with(
                'error',
                'Cannot delete an ingredient that is used in a product recipe.'
            );
        }

        DB::transaction(function () use ($ingredient) {
            $ingredient->batches()->delete();
            $ingredient->delete();
        });

        return back()->with('success', 'Ingredient deleted successfully.');
    }

    /**
     * Batch list for a single ingredient.
     */
    public function batches(Ingredient $ingredient): JsonResponse
    {
        return response()->json([
            'batches' => $ingredient
                ->batches()
                ->orderBy('expiry_date')
                ->get(),
        ]);
    }

    public function restock(
        Request $request,
        Ingredient $ingredient
    ): RedirectResponse {
        $validator = validator($request->all(), [
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', Rule::in(['g', 'kg', 'ml', 'l', 'pcs'])],
            'received_date' => ['nullable', 'date'],
            'expiry_date' => [
                'nullable',
                'date',
                'after_or_equal:received_date',
            ],
            'total_cost' => ['nullable', 'integer', 'min:0'],
        ]);

        $validator->after(
            function (Validator $validator) use ($ingredient) {
                $unit = $validator->getData()['unit'] ?? null;

                if (
                    $unit
                    && ! $this->units->validateCompatibleUnits(
                        $unit,
                        $ingredient->unit
                    )
                ) {
                    $validator->errors()->add(
                        'unit',
                        "Unit [{$unit}] is not compatible with this ingredient's measurement type ({$ingredient->measurement_type})."
                    );
                }
            }
        );

        $validated = $validator->validate();

        $this->inventory->addBatch(
            $ingredient,
            (float) $validated['quantity'],
            $validated['unit'],
            $validated['received_date'] ?? null,
            $validated['expiry_date'] ?? null,
            $validated['total_cost'] ?? null,
        );

        return back()->with('success', 'Batch added successfully.');
    }

    /**
     * Downloads the full 5-sheet Inventory Monitoring Report as .xlsx.
     */
    public function exportInventory(
        Request $request
    ): BinaryFileResponse {
        [$meta, $data] = $this->buildInventoryReportData($request);

        $filename =
            'JC66-Inventory-Report-'
            . now()->format('Y-m-d')
            . '.xlsx';

        return Excel::download(
            new InventoryReportExport($meta, $data),
            $filename
        );
    }

    /**
     * Downloads a single Inventory Monitoring Report section as .xlsx.
     */
    public function exportInventorySheet(
        Request $request,
        string $sheet
    ): BinaryFileResponse {
        [$meta, $data] = $this->buildInventoryReportData($request);

        [$export, $label] = match ($sheet) {
            'summary' => [
                new \App\Exports\Sheets\InventorySummarySheet(
                    $meta,
                    $data['summary']
                ),
                'Inventory-Summary',
            ],

            'stock-in' => [
                new \App\Exports\Sheets\StockInSheet(
                    $meta,
                    $data['stockIn']
                ),
                'Stock-In-Restocking',
            ],

            'movement' => [
                new \App\Exports\Sheets\InventoryMovementSheet(
                    $meta,
                    $data['movement']
                ),
                'Inventory-Movement',
            ],

            'batch-expiry' => [
                new \App\Exports\Sheets\BatchExpirySheet(
                    $meta,
                    $data['batches']
                ),
                'Batch-Expiry',
            ],

            'low-stock' => [
                new \App\Exports\Sheets\LowStockSheet(
                    $meta,
                    $data['lowStock']
                ),
                'Low-Stock-Report',
            ],

            default => abort(
                404,
                "Unknown report sheet [{$sheet}]."
            ),
        };

        $filename =
            'JC66-'
            . $label
            . '-'
            . now()->format('Y-m-d')
            . '.xlsx';

        return Excel::download($export, $filename);
    }

    /**
     * Downloads the full 5-section Inventory Monitoring Report as Word.
     *
     * Same data and date filtering as the Excel export.
     */
    public function exportInventoryWord(
        Request $request
    ): BinaryFileResponse {
        [$meta, $data] = $this->buildInventoryReportData($request);

        $filename =
            'JC66-Inventory-Report-'
            . now()->format('Y-m-d')
            . '.docx';

        $path = (new InventoryReportWordExport(
            $meta,
            $data
        ))->save();

        return response()
            ->download($path, $filename)
            ->deleteFileAfterSend(true);
    }

    /**
     * Downloads a single Inventory Monitoring Report section as Word.
     *
     * Supported sections:
     *
     * summary
     * stock-in
     * movement
     * batch-expiry
     * low-stock
     *
     * Example:
     * /reports/inventory/export/word/low-stock
     */
    public function exportInventoryWordSection(
        Request $request,
        string $sheet
    ): BinaryFileResponse {
        [$meta, $data] = $this->buildInventoryReportData($request);

        $labels = [
            'summary' => 'Inventory-Summary',
            'stock-in' => 'Stock-In-Restocking',
            'movement' => 'Inventory-Movement',
            'batch-expiry' => 'Batch-Expiry',
            'low-stock' => 'Low-Stock-Report',
        ];

        if (! isset($labels[$sheet])) {
            abort(
                404,
                "Unknown report section [{$sheet}]."
            );
        }

        $filename =
            'JC66-'
            . $labels[$sheet]
            . '-'
            . now()->format('Y-m-d')
            . '.docx';

        $path = (new InventoryReportWordExport(
            $meta,
            $data
        ))->saveSection($sheet);

        return response()
            ->download($path, $filename)
            ->deleteFileAfterSend(true);
    }

    /**
     * Shared data builder for:
     *
     * - Excel full report
     * - Excel section reports
     * - Word full report
     * - Word section reports
     *
     * @return array{
     *     0: array{
     *         period:string,
     *         generated_by:string,
     *         generated_date:string
     *     },
     *     1: array
     * }
     */
    private function buildInventoryReportData(
        Request $request
    ): array {
        $dateFrom =
            $request->date('date_from')
            ?? now()->startOfMonth();

        $dateTo =
            $request->date('date_to')
            ?? now()->endOfDay();

        $ingredients = Ingredient::query()
            ->with('validBatches')
            ->orderBy('name')
            ->get();

        // ============================================================
        // SHEET 1: INVENTORY SUMMARY
        // ============================================================

        $summaryRows = $ingredients
            ->map(fn (Ingredient $i) => [
                $i->name,
                (float) $i->total_stock,
                $i->unit,
                $i->unit_cost !== null
                    ? (float) $i->unit_cost
                    : null,
                $i->total_value,
                (float) $i->minimum_stock,
                Str::headline($i->status),
            ])
            ->all();

        // ============================================================
        // SHEET 2: STOCK-IN / RESTOCKING
        // ============================================================

        $stockInLogs = InventoryLog::query()
            ->with([
                'ingredient:id,name,unit',
                'ingredientBatch',
            ])
            ->where('type', 'restock')
            ->whereNotNull('ingredient_id')
            ->whereBetween(
                'created_at',
                [$dateFrom, $dateTo]
            )
            ->latest()
            ->get();

        $stockInRows = $stockInLogs
            ->map(fn (InventoryLog $log) => [
                $log->created_at->format('Y-m-d H:i'),
                $log->ingredient?->name ?? '—',
                $log->ingredient_batch_id,
                (float) $log->quantity_change,
                $log->ingredientBatch?->unit
                    ?? $log->ingredient?->unit
                    ?? '',
                $log->ingredientBatch?->received_date
                    ?->format('Y-m-d')
                    ?? '—',
                $log->ingredientBatch?->expiry_date
                    ?->format('Y-m-d')
                    ?? '—',
                $log->ingredientBatch?->total_cost,
                $log->note ?? '—',
            ])
            ->all();

        // ============================================================
        // SHEET 3: INVENTORY MOVEMENT
        // ============================================================

        $movementLogs = InventoryLog::query()
            ->with([
                'ingredient:id,name,unit',
                'product:id,name',
            ])
            ->whereBetween(
                'created_at',
                [$dateFrom, $dateTo]
            )
            ->latest()
            ->get();

        $movementRows = $movementLogs
            ->map(fn (InventoryLog $log) => [
                $log->created_at->format('Y-m-d H:i'),
                $log->ingredient?->name
                    ?? $log->product?->name
                    ?? '—',
                Str::headline($log->type),
                (float) $log->quantity_change,
                $log->ingredient?->unit ?? 'pcs',
                $log->note ?? '—',
            ])
            ->all();

        // ============================================================
        // SHEET 4: BATCH & EXPIRY
        // ============================================================

        $batches = IngredientBatch::query()
            ->with('ingredient:id,name')
            ->where('remaining_quantity', '>', 0)
            ->orderByRaw(
                'expiry_date IS NULL, expiry_date ASC'
            )
            ->get();

        $batchRows = $batches
            ->map(fn (IngredientBatch $b) => [
                $b->ingredient?->name ?? '—',
                $b->id,
                $b->received_date?->format('Y-m-d') ?? '—',
                $b->expiry_date?->format('Y-m-d') ?? '—',
                (float) $b->remaining_quantity,
                $b->unit,
                Str::headline($b->status),
            ])
            ->all();

        // ============================================================
        // SHEET 5: LOW STOCK
        // ============================================================

        $lowStockRows = $ingredients
            ->filter(
                fn (Ingredient $i) => in_array(
                    $i->status,
                    ['low_stock', 'out_of_stock'],
                    true
                )
            )
            ->map(function (Ingredient $i) {
                $shortage = max(
                    (float) $i->minimum_stock
                    - (float) $i->total_stock,
                    0
                );

                return [
                    $i->name,
                    (float) $i->total_stock,
                    $i->unit,
                    (float) $i->minimum_stock,
                    round($shortage, 2),
                    round($shortage, 2),
                    Str::headline($i->status),
                ];
            })
            ->values()
            ->all();

        // ============================================================
        // REPORT META
        // ============================================================

        $meta = [
            'period' =>
                $dateFrom->format('M j, Y')
                . ' - '
                . $dateTo->format('M j, Y'),

            'generated_by' =>
                $request->user()?->name
                ?? 'Manager',

            'generated_date' =>
                now()->format('M j, Y g:i A'),
        ];

        return [
            $meta,
            [
                'summary' => $summaryRows,
                'stockIn' => $stockInRows,
                'movement' => $movementRows,
                'batches' => $batchRows,
                'lowStock' => $lowStockRows,
            ],
        ];
    }

    /**
     * Admin/manager inventory report.
     */
    public function inventoryReport(
        Request $request
    ): Response {
        $ingredients = Ingredient::query()
            ->with('validBatches')
            ->orderBy('name')
            ->get();

        $lowStock = $ingredients
            ->filter(
                fn (Ingredient $i) =>
                    $i->status === 'low_stock'
            )
            ->values();

        $outOfStock = $ingredients
            ->filter(
                fn (Ingredient $i) =>
                    $i->status === 'out_of_stock'
            )
            ->values();

        $warningDays =
            config('inventory.expiry_warning_days', 5);

        $expiringSoon = IngredientBatch::query()
            ->with('ingredient')
            ->where('remaining_quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereBetween(
                'expiry_date',
                [
                    now()->startOfDay(),
                    now()
                        ->addDays($warningDays)
                        ->endOfDay(),
                ]
            )
            ->orderBy('expiry_date')
            ->get();

        $restockHistory = InventoryLog::query()
            ->with([
                'ingredient:id,name,unit',
                'ingredientBatch:id,received_date,expiry_date,total_cost',
                'user:id,name',
            ])
            ->where('type', 'restock')
            ->whereNotNull('ingredient_id')
            ->latest()
            ->limit(20)
            ->get();

        $productRestockHistory = InventoryLog::query()
            ->with(['product:id,name', 'user:id,name'])
            ->whereNotNull('product_id')
            ->latest()
            ->limit(20)
            ->get();

        $stockValueItems = $ingredients
            ->filter(
                fn (Ingredient $i) =>
                    $i->unit_cost !== null
            )
            ->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'total_stock' => $i->total_stock,
                'unit' => $i->unit,
                'unit_cost' => $i->unit_cost,
                'total_value' => $i->total_value,
            ])
            ->values();

        // Finished products (pastry / finished stock) valued at their price.
        $finishedStockValueItems = Product::query()
            ->where('tracking_type', 'finished_stock')
            ->where('stock_quantity', '>', 0)
            ->get()
            ->map(function (Product $product) {
                $qty = (int) $product->stock_quantity;
                $price = (float) $product->price;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock_quantity' => $qty,
                    'unit' => 'pcs',
                    'price' => $product->price,
                    'total_value' => round($qty * $price, 2),
                ];
            })
            ->values();

        $ingredientValue = $stockValueItems->sum('total_value');
        $finishedValue = $finishedStockValueItems->sum('total_value');

        $totalStockValue =
            $stockValueItems->isEmpty() && $finishedStockValueItems->isEmpty()
                ? null
                : round($ingredientValue + $finishedValue, 2);

        return Inertia::render(
            'Reports/Inventory',
            [
                'summary' => [
                    'total_ingredients' =>
                        $ingredients->count(),

                    'total_stock_value' =>
                        $totalStockValue,

                    'low_stock_count' =>
                        $lowStock->count(),

                    'expiring_soon_count' =>
                        $expiringSoon->count(),

                    'out_of_stock_count' =>
                        $outOfStock->count(),
                ],

                'lowStockItems' =>
                    $lowStock->map(
                        fn (Ingredient $i) => [
                            'id' => $i->id,
                            'name' => $i->name,
                            'total_stock' => $i->total_stock,
                            'minimum_stock' =>
                                (float) $i->minimum_stock,
                            'unit' => $i->unit,
                        ]
                    ),

                'outOfStockItems' =>
                    $outOfStock->map(
                        fn (Ingredient $i) => [
                            'id' => $i->id,
                            'name' => $i->name,
                            'unit' => $i->unit,
                        ]
                    ),

                'expiringSoon' =>
                    $expiringSoon->map(
                        fn (IngredientBatch $b) => [
                            'id' => $b->id,
                            'ingredient_name' =>
                                $b->ingredient->name,
                            'remaining_quantity' =>
                                (float) $b->remaining_quantity,
                            'unit' => $b->unit,
                            'expiry_date' =>
                                $b->expiry_date->toDateString(),
                        ]
                    ),

                'restockHistory' =>
                    $restockHistory->map(
                        fn (InventoryLog $log) => [
                            'id' => $log->id,
                            'ingredient_name' =>
                                $log->ingredient?->name ?? '—',
                            'unit' =>
                                $log->ingredient?->unit ?? '',
                            'quantity_change' =>
                                (float) $log->quantity_change,
                            'received_date' =>
                                $log->ingredientBatch
                                    ?->received_date
                                    ?->toDateString(),
                            'expiry_date' =>
                                $log->ingredientBatch
                                    ?->expiry_date
                                    ?->toDateString(),
                'price' =>
                    $log->ingredientBatch?->total_cost,
                'note' => $log->note,
                'user_name' =>
                    $log->user?->name ?? null,
                'created_at' =>
                    $log->created_at
                        ->toDateTimeString(),
            ]
                    ),

                'productRestockHistory' =>
                    $productRestockHistory->map(
                        fn (InventoryLog $log) => [
                            'id' => $log->id,
                            'product_name' =>
                                $log->product?->name ?? '—',
                            'type' => $log->type,
                'quantity_change' =>
                    (float) $log->quantity_change,
                'note' => $log->note,
                'user_name' =>
                    $log->user?->name ?? null,
                'created_at' =>
                    $log->created_at
                        ->toDateTimeString(),
            ]
                    ),

                'stockValueItems' =>
                    $stockValueItems,
                'finishedStockValueItems' =>
                    $finishedStockValueItems,
            ]
        );
    }

    private function validateIngredient(
        Request $request,
        ?Ingredient $ingredient = null
    ): array {
        $validator = validator(
            $request->all(),
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('ingredients', 'name')
                        ->ignore($ingredient?->id),
                ],

                'measurement_type' => [
                    'required',
                    Rule::in([
                        'weight',
                        'volume',
                        'piece',
                    ]),
                ],

                'unit' => [
                    'required',
                    Rule::in([
                        'g',
                        'kg',
                        'ml',
                        'l',
                        'pcs',
                    ]),
                ],

                'minimum_stock' => [
                    'required',
                    'numeric',
                    'min:0',
                ],

                'unit_cost' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'quantity' => $ingredient
                    ? ['prohibited']
                    : ['nullable', 'numeric', 'min:0'],

                'received_date' => [
                    'nullable',
                    'date',
                ],

                'expiry_date' => [
                    'nullable',
                    'date',
                    'after_or_equal:received_date',
                ],
            ]
        );

        $validator->after(
            function (Validator $validator) {
                $type =
                    $validator
                        ->getData()['measurement_type']
                        ?? null;

                $unit =
                    $validator
                        ->getData()['unit']
                        ?? null;

                if (
                    $type
                    && $unit
                    && $this->units->getMeasurementType($unit)
                        !== $type
                ) {
                    $validator->errors()->add(
                        'unit',
                        "Unit [{$unit}] does not belong to measurement type [{$type}]."
                    );
                }
            }
        );

        return $validator->validate();
    }
}