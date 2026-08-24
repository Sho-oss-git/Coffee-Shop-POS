<?php

namespace App\Exports\Sheets;

/**
 * Rows come from InventoryLog::with(['ingredient','product']) across ALL
 * types, mapped as:
 * [created_at, item_name, type_label, quantity_change, unit, note]
 *
 * `type` is the real enum: restock | sale | adjustment | expired.
 */
class InventoryMovementSheet extends BaseReportSheet
{
    public function title(): string
    {
        return 'Inventory Movement';
    }

    public function reportHeading(): string
    {
        return '3. Stock Movement / Inventory Logs';
    }

    public function headings(): array
    {
        return ['Date & Time', 'Item', 'Type', 'Quantity Change', 'Unit', 'Note'];
    }

    public function currencyColumns(): array
    {
        return [];
    }
}