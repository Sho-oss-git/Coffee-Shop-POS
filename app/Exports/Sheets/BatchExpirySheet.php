<?php

namespace App\Exports\Sheets;

/**
 * Rows come from IngredientBatch::with('ingredient')->where('remaining_quantity', '>', 0),
 * mapped as:
 * [ingredient_name, batch_id, received_date, expiry_date, remaining_quantity, unit, status_label]
 *
 * `status` is the computed accessor on IngredientBatch: active | expiring_soon | expired.
 */
class BatchExpirySheet extends BaseReportSheet
{
    public function title(): string
    {
        return 'Batch and Expiry';
    }

    public function reportHeading(): string
    {
        return '4. Batch & Expiry Report';
    }

    public function headings(): array
    {
        return ['Ingredient', 'Batch #', 'Received Date', 'Expiry Date', 'Remaining Qty', 'Unit', 'Status'];
    }

    public function currencyColumns(): array
    {
        return [];
    }

    public function statusColumn(): ?int
    {
        return 7;
    }
}