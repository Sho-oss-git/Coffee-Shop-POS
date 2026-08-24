<?php

namespace App\Exports\Sheets;

/**
 * Rows come from TransactionController::productBreakdown(), mapped as:
 * [product, category, quantity_sold, unit_price, revenue, cogs, gross_profit, gross_margin]
 *
 * "category" is Product::$category — a plain string column, not a
 * relation (confirmed from Product's $fillable).
 */
class ProductSalesSheet extends BaseReportSheet
{
    public function title(): string
    {
        return 'Product Sales';
    }

    public function reportHeading(): string
    {
        return '3. Product Sales Breakdown';
    }

    public function headings(): array
    {
        return ['Product', 'Category', 'Quantity Sold', 'Unit Price (₱)', 'Revenue (₱)', 'COGS (₱)', 'Gross Profit (₱)', 'Gross Margin (%)'];
    }

    public function currencyColumns(): array
    {
        return [4, 5, 6, 7];
    }

    public function percentageColumns(): array
    {
        return [8];
    }

    public function footerRows(): array
    {
        if ($this->rows === []) {
            return [];
        }

        $qty = array_sum(array_map(fn (array $r) => (float) ($r[2] ?? 0), $this->rows));
        $revenue = array_sum(array_map(fn (array $r) => (float) ($r[4] ?? 0), $this->rows));
        $cogs = array_sum(array_map(fn (array $r) => (float) ($r[5] ?? 0), $this->rows));
        $profit = round($revenue - $cogs, 2);
        $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0;

        return [
            ['TOTAL', '', $qty, '', round($revenue, 2), round($cogs, 2), $profit, $margin],
        ];
    }
}   