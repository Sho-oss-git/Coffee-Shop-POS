<?php

namespace App\Exports;

use App\Exports\Sheets\TransactionLogSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Builds the printable Sales Report as a single Transaction Log sheet,
 * with a Total Sales row at the bottom (see TransactionLogSheet::footerRows()).
 *
 * Usage: Excel::download(new SalesReportExport($meta, $data), 'JC66-Sales-Report.xlsx');
 *
 * @param  array{period:string,generated_by:string,generated_date:string}  $meta
 * @param  array{transactionLog: array<int,array>}  $data
 */
class SalesReportExport implements WithMultipleSheets
{
    public function __construct(private array $meta, private array $data)
    {
    }

    public function sheets(): array
    {
        return [
            new TransactionLogSheet($this->meta, $this->data['transactionLog']),
        ];
    }
}