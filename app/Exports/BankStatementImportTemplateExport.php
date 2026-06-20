<?php

namespace App\Exports;

use App\Services\TransactionImportColumnMap;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BankStatementImportTemplateExport implements FromArray, WithHeadings, WithTitle
{
    public function headings(): array
    {
        return TransactionImportColumnMap::templateHeadings();
    }

    public function array(): array
    {
        return TransactionImportColumnMap::templateExampleRows();
    }

    public function title(): string
    {
        return 'Import template';
    }
}
