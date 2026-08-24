<?php

namespace App\Exports\Sheets;

/**
 * Rows come from Ingredient::with('validBatches')->get(), mapped as:
 * [name, total_stock, unit, unit_cost, total_value, minimum_stock, status_label]
 *
 * total_stock / status / total_value are computed accessors on the
 * Ingredient model (App\Models\Ingredient) — not raw columns.
 */
class InventorySummarySheet extends BaseReportSheet
{
    public function title(): string
    {
        return 'Inventory Summary';
    }

    public function reportHeading(): string
    {
        return '1. Inventory Summary';
    }

    public function headings(): array
    {
        return ['Ingredient', 'Current Stock', 'Unit', 'Unit Cost (₱)', 'Total Value (₱)', 'Minimum Stock', 'Status'];
    }

    public function currencyColumns(): array
    {
        return [4, 5];
    }

    public function statusColumn(): ?int
    {
        return 7;
    }

    public function footerRows(): array
    {
        if ($this->rows === []) {
            return [];
        }

        $total = array_sum(array_map(fn (array $r) => (float) ($r[4] ?? 0), $this->rows));

        return [
            ['', '', '', 'TOTAL INVENTORY VALUE', $total, '', ''],
        ];
    }
}