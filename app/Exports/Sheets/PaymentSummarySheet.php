<?php

namespace App\Exports\Sheets;

/**
 * Rows: [label, transactions, total_sales, percentage]
 */
class PaymentSummarySheet extends BaseReportSheet
{
    public function title(): string
    {
        return 'Payment Summary';
    }

    public function reportHeading(): string
    {
        return '4. Payment Method Summary';
    }

    public function headings(): array
    {
        return ['Payment Method', 'Transactions', 'Total Sales (₱)', 'Percentage (%)'];
    }

    public function currencyColumns(): array
    {
        return [3];
    }

    public function percentageColumns(): array
    {
        return [4];
    }

    public function footerRows(): array
    {
        if ($this->rows === []) {
            return [];
        }

        $tx = array_sum(array_map(fn (array $r) => (float) ($r[1] ?? 0), $this->rows));
        $sales = array_sum(array_map(fn (array $r) => (float) ($r[2] ?? 0), $this->rows));

        return [
            ['TOTAL', $tx, round($sales, 2), 100],
        ];
    }
}