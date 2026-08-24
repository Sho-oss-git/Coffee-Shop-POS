<?php

namespace App\Exports\Sheets;

/**
 * Rows filtered from the same Ingredient collection as InventorySummarySheet,
 * to status in [low_stock, out_of_stock], mapped as:
 * [name, total_stock, unit, minimum_stock, shortage, suggested_restock, status_label]
 *
 * `shortage` = max(minimum_stock - total_stock, 0).
 * `suggested_restock` = shortage (brings stock back to minimum — the schema
 * has no "par level" field to suggest a bigger buffer).
 */
class LowStockSheet extends BaseReportSheet
{
    public function title(): string
    {
        return 'Low Stock Report';
    }

    public function reportHeading(): string
    {
        return '5. Low Stock / Restock Report';
    }

    public function headings(): array
    {
        return ['Ingredient', 'Current Stock', 'Unit', 'Minimum Stock', 'Shortage', 'Suggested Restock', 'Status'];
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