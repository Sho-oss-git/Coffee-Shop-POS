<?php

namespace App\Exports\Sheets;

/**
 * Rows come from TransactionController::transactionLogRows(), mapped as:
 * [txn_no, date, time, cashier, order_number, order_type, payment_method,
 *  gcash_reference_number, amount_received, change, total, status, note,
 *  created_at]
 *
 * Includes completed, refunded, and voided transactions (spec section 13).
 * TOTAL SALES footer only sums rows where status = "Completed" — refunds
 * and voids are visible here but never counted as sales.
 */
class TransactionLogSheet extends BaseReportSheet
{
    public function title(): string
    {
        return 'Transaction Log';
    }

    public function reportHeading(): string
    {
        return 'Transaction Log';
    }

    public function headings(): array
    {
        return ['Transaction #', 'Date', 'Time', 'Cashier', 'Order #', 'Order Type', 'Payment Method', 'GCash Reference #', 'Amount Received (₱)', 'Change (₱)', 'Total (₱)', 'Status', 'Notes', 'Created At'];
    }

    public function currencyColumns(): array
    {
        return [9, 10, 11];
    }

    public function statusColumn(): ?int
    {
        return 12;
    }

    public function footerRows(): array
    {
        if ($this->rows === []) {
            return [];
        }

        // Total (col 11) is array index 10; Status (col 12) is array index 11.
        $totalSales = array_sum(array_map(
            fn (array $r) => ($r[11] ?? '') === 'Completed' ? (float) ($r[10] ?? 0) : 0,
            $this->rows
        ));

        return [
            ['', '', '', '', '', '', '', '', '', 'TOTAL SALES', round($totalSales, 2), '', '', ''],
        ];
    }
}