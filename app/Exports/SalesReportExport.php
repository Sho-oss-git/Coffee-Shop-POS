<?php

namespace App\Exports;

use App\Exports\Sheets\PaymentSummarySheet;
use App\Exports\Sheets\ProductSalesSheet;
use App\Exports\Sheets\SalesByPeriodSheet;
use App\Exports\Sheets\SalesSummarySheet;
use App\Exports\Sheets\TransactionLogSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Builds the printable 5-sheet Sales Report:
 *   1. Sales Summary (KPIs + Payment/Order Type Summary + Best Sellers)
 *   2. Transaction Log
 *   3. Product Sales
 *   4. Payment Summary
 *   5. Sales by Period
 *
 * Usage: Excel::download(new SalesReportExport($meta, $data), 'JC66-Sales-Report.xlsx');
 */
class SalesReportExport implements WithMultipleSheets
{
    public function __construct(private array $meta, private array $data)
    {
    }

    public function sheets(): array
    {
        return [
            new SalesSummarySheet($this->meta, $this->data),
            new TransactionLogSheet($this->meta, $this->data['transactionLog']),
            new ProductSalesSheet($this->meta, $this->data['productSales']),
            new PaymentSummarySheet($this->meta, $this->data['paymentSummaryRows']),
            new SalesByPeriodSheet($this->meta, $this->data['salesByPeriodRows']),
        ];
    }
}