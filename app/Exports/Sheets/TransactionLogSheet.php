<?php

namespace App\Exports\Sheets;

/**
 * Rows come from TransactionController::transactionLogRows(), mapped as:
 * [txn_no, date, time, cashier, order_number, order_type, payment_method,
 *  gcash_reference_number, amount_received, change, total, status, created_at]
 */
class TransactionLogSheet extends BaseReportSheet
{
    public function title(): string
    {
        return 'Transaction Log';
    }

    public function reportHeading(): string
    {
        return '2. Transaction Log';
    }

    public function headings(): array
    {
        return ['Transaction #', 'Date', 'Time', 'Cashier', 'Order #', 'Order Type', 'Payment Method', 'GCash Reference #', 'Amount Received (₱)', 'Change (₱)', 'Total (₱)', 'Status', 'Created At'];
    }

    public function currencyColumns(): array
    {
        return [9, 10, 11];
    }
}
