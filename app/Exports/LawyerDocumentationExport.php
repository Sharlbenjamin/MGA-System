<?php

namespace App\Exports;

use App\Exports\Sheets\LawyerArraySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LawyerDocumentationExport implements WithMultipleSheets
{
    public function __construct(
        protected array $payload,
    ) {}

    public function sheets(): array
    {
        return [
            new LawyerArraySheet(
                'Transfers',
                $this->payload['transfers']['headings'],
                $this->payload['transfers']['rows'],
            ),
            new LawyerArraySheet(
                'Payables',
                $this->payload['payables']['headings'],
                $this->payload['payables']['rows'],
            ),
            new LawyerArraySheet(
                'Receivables',
                $this->payload['receivables']['headings'],
                $this->payload['receivables']['rows'],
            ),
            new LawyerArraySheet(
                'Clients',
                $this->payload['clients']['headings'],
                $this->payload['clients']['rows'],
            ),
        ];
    }
}
