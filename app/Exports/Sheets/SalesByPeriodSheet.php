<?php

namespace App\Exports\Sheets;

/**
 * Rows: [period, transactions, items_sold, sales, cogs, gross_profit, gross_margin]
 */
class SalesByPeriodSheet extends BaseReportSheet
{
    public function title(): string
    {
        return 'Sales by Period';
    }

    public function reportHeading(): string
    {
        return '5. Sales by Period';
    }

    public function headings(): array
    {
        return ['Period', 'Transactions', 'Items Sold', 'Sales (₱)', 'COGS (₱)', 'Gross Profit (₱)', 'Gross Margin (%)'];
    }

    public function currencyColumns(): array
    {
        return [4, 5, 6];
    }

    public function percentageColumns(): array
    {
        return [7];
    }
}