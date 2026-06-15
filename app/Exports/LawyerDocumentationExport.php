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
                'Transactions',
                $this->payload['transactions']['headings'],
                $this->payload['transactions']['rows'],
            ),
            new LawyerArraySheet(
                'Receivables',
                $this->payload['receivables_summary']['headings'],
                $this->payload['receivables_summary']['rows'],
            ),
            new LawyerArraySheet(
                'Receivable Invoices',
                $this->payload['receivable_invoices']['headings'],
                $this->payload['receivable_invoices']['rows'],
            ),
            new LawyerArraySheet(
                'Payments & Expenses',
                $this->payload['payments_summary']['headings'],
                $this->payload['payments_summary']['rows'],
            ),
            new LawyerArraySheet(
                'Payment & Expense Detail',
                $this->payload['payment_detail']['headings'],
                $this->payload['payment_detail']['rows'],
            ),
            new LawyerArraySheet(
                'Clients',
                $this->payload['clients']['headings'],
                $this->payload['clients']['rows'],
            ),
        ];
    }
}
