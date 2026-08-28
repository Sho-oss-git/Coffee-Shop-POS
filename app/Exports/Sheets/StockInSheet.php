<?php

namespace App\Exports\Sheets;

/**
 * Rows come from InventoryLog::where('type', 'restock')->with(['ingredient','ingredientBatch']),
 * mapped as:
 * [date_logged, ingredient_name, batch_id, quantity_added, unit, received_date, expiry_date, batch_total_cost, note]
 *
 * NOTE: the current schema doesn't track a Supplier or "Received By" user —
 * those columns from the original mock report aren't included here since
 * there's no real data behind them yet.
 */
class StockInSheet extends BaseReportSheet
{
    public function title(): string
    {
        return 'Stock In-Restocking';
    }

    public function reportHeading(): string
    {
        return '2. Stock-In / Restocking Record';
    }

    public function headings(): array
    {
        return ['Date Logged', 'Ingredient', 'Batch #', 'Quantity Added', 'Unit', 'Batch Received', 'Batch Expiry', 'Old Stock Cost (₱)', 'New Stock Cost (₱)', 'Note'];
    }

    public function currencyColumns(): array
    {
        return [8, 9];
    }

    public function footerRows(): array
    {
        if ($this->rows === []) {
            return [];
        }

        $newTotal = array_sum(array_map(fn (array $r) => (float) ($r[8] ?? 0), $this->rows));
        $oldTotal = array_sum(array_map(fn (array $r) => (float) ($r[7] ?? 0), $this->rows));

        return [
            ['', '', '', '', '', '', 'TOTAL OLD COST', $oldTotal, '', ''],
            ['', '', '', '', '', '', 'TOTAL NEW COST', '', $newTotal, ''],
        ];
    }
}