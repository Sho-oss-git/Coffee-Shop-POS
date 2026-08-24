<?php

namespace App\Exports;

use App\Exports\Sheets\BatchExpirySheet;
use App\Exports\Sheets\InventoryMovementSheet;
use App\Exports\Sheets\InventorySummarySheet;
use App\Exports\Sheets\LowStockSheet;
use App\Exports\Sheets\StockInSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Builds the printable 5-sheet Inventory Monitoring Report:
 *   1. Inventory Summary
 *   2. Stock In / Restocking
 *   3. Inventory Movement
 *   4. Batch & Expiry
 *   5. Low Stock Report
 *
 * Usage: Excel::download(new InventoryReportExport($meta, $data), 'JC66-Inventory-Report.xlsx');
 *
 * @param  array{period:string,generated_by:string,generated_date:string}  $meta
 * @param  array{summary:array,stockIn:array,movement:array,batches:array,lowStock:array}  $data
 */
class InventoryReportExport implements WithMultipleSheets
{
    public function __construct(private array $meta, private array $data)
    {
    }

    public function sheets(): array
    {
        return [
            new InventorySummarySheet($this->meta, $this->data['summary']),
            new StockInSheet($this->meta, $this->data['stockIn']),
            new InventoryMovementSheet($this->meta, $this->data['movement']),
            new BatchExpirySheet($this->meta, $this->data['batches']),
            new LowStockSheet($this->meta, $this->data['lowStock']),
        ];
    }
}