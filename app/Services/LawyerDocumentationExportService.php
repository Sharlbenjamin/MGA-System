<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LawyerDocumentationExportService
{
    public function __construct(
        protected DocumentLinkResolver $linkResolver,
        protected TransactionDocumentationService $documentationService,
    ) {}

    public function buildExportPayload(
        int $year,
        string $quarter,
        float $ivaPercent,
        string $nifSource,
    ): array {
        [$startDate, $endDate] = TaxExportHelpers::resolvePeriodDates($year, $quarter);
        $ivaRate = $ivaPercent / 100;

        $transactions = $this->loadPeriodTransactions($startDate, $endDate);
        $incomeTransactions = $transactions->where('type', 'Income')->values();
        $paymentTransactions = $transactions->whereIn('type', ['Outflow', 'Expense'])->values();

        $receivableInvoices = $this->collectReceivableInvoices($incomeTransactions);

        $filenameQuarter = $quarter === 'full' ? 'full' : 'Q'.$quarter;
        $filename = "lawyer_documentation_{$year}_{$filenameQuarter}_".now()->format('Y-m-d_H-i-s').'.xlsx';

        return [
            'filename' => $filename,
            'transactions' => [
                'headings' => $this->transactionsHeadings(),
                'rows' => $this->buildTransactionsRows($transactions),
            ],
            'receivables_summary' => [
                'headings' => $this->receivablesSummaryHeadings(),
                'rows' => $this->buildReceivablesSummaryRows($incomeTransactions),
            ],
            'receivable_invoices' => [
                'headings' => $this->receivableInvoicesHeadings(),
                'rows' => $this->buildReceivableInvoiceRows($receivableInvoices, $ivaPercent, $ivaRate, $nifSource),
            ],
            'payments_summary' => [
                'headings' => $this->paymentsSummaryHeadings(),
                'rows' => $this->buildPaymentsSummaryRows($paymentTransactions),
            ],
            'payment_detail' => [
                'headings' => $this->paymentDetailHeadings(),
                'rows' => $this->buildPaymentDetailRows($paymentTransactions, $nifSource),
            ],
            'clients' => [
                'headings' => $this->clientsHeadings(),
                'rows' => $this->buildClientsRows($receivableInvoices),
            ],
        ];
    }

    protected function loadPeriodTransactions($startDate, $endDate): Collection
    {
        return $this->internalBankTransactionsQuery($startDate, $endDate)
            ->with([
                'invoices.file.serviceType',
                'invoices.patient.client',
                'bills.file.patient.client',
                'bills.file.serviceType',
                'bills.provider',
                'attachments',
                'bankAccount',
            ])
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, array{transaction: Transaction, invoice: Invoice}>
     */
    protected function collectReceivableInvoices(Collection $incomeTransactions): Collection
    {
        $items = collect();

        foreach ($incomeTransactions as $transaction) {
            foreach ($transaction->invoices as $invoice) {
                $items->push([
                    'transaction' => $transaction,
                    'invoice' => $invoice,
                ]);
            }
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    protected function transactionsHeadings(): array
    {
        return [
            'Transaction ID',
            'Group ID',
            'Date',
            'Type',
            'Category',
            'Direction',
            'Amount',
            'Reference',
            'Description',
            'Related Party',
            'Linked Invoice Count',
            'Linked Bill Count',
            'Documentation Status',
            'Documentation Notes',
            'Attachment Links',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function buildTransactionsRows(Collection $transactions): array
    {
        $rows = [];

        foreach ($transactions as $transaction) {
            $reference = $transaction->reference ?: $transaction->name;
            $description = $transaction->notes ?: $transaction->name ?: $reference;

            $rows[] = [
                $transaction->id,
                $transaction->id,
                $transaction->date?->format('Y-m-d'),
                $transaction->type,
                $this->documentationService->getDocumentationLabel($transaction),
                $this->documentationService->getDirection($transaction),
                round(abs((float) $transaction->amount), 2),
                $reference,
                $description,
                $transaction->getRelatedPartyLabel() ?? '-',
                $transaction->invoices->count(),
                $transaction->bills->count(),
                $this->documentationService->formatDocumentationStatusLabel($transaction->documentation_status),
                $this->documentationService->getPendingTaskSummary($transaction) ?? '',
                $this->linkResolver->transactionTabOneLinks($transaction),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    protected function receivablesSummaryHeadings(): array
    {
        return [
            'Group ID',
            'Transaction ID',
            'Payment Date',
            'Client Name',
            'Amount',
            'Invoice Count',
            'Invoice Numbers',
            'Documentation Status',
            'Trx In PDF Link',
            'Receipt Link',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function buildReceivablesSummaryRows(Collection $incomeTransactions): array
    {
        $rows = [];

        foreach ($incomeTransactions as $transaction) {
            $clientName = $transaction->getRelatedPartyLabel()
                ?? $transaction->invoices->first()?->patient?->client?->company_name
                ?? '-';

            $rows[] = [
                $transaction->id,
                $transaction->id,
                $transaction->date?->format('Y-m-d'),
                $clientName,
                round(abs((float) $transaction->amount), 2),
                $transaction->invoices->count(),
                $transaction->invoices->pluck('name')->implode(', ') ?: '-',
                $this->documentationService->formatDocumentationStatusLabel($transaction->documentation_status),
                $this->linkResolver->trxInLink($transaction),
                $this->linkResolver->transactionReceiptLinks($transaction),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    protected function receivableInvoicesHeadings(): array
    {
        return [
            'Group ID',
            'Transaction ID',
            'Invoice Date',
            'Invoice Number',
            'Service',
            'Client Name',
            'Client Country',
            'NIF',
            'Patient Name',
            'Total Amount',
            'IVA %',
            'Total After IVA',
            'Status',
            'Invoice Attachment Links',
        ];
    }

    /**
     * @param  Collection<int, array{transaction: Transaction, invoice: Invoice}>  $receivableInvoices
     * @return array<int, array<int, mixed>>
     */
    protected function buildReceivableInvoiceRows(
        Collection $receivableInvoices,
        float $ivaPercent,
        float $ivaRate,
        string $nifSource,
    ): array {
        $rows = [];

        foreach ($receivableInvoices as $item) {
            /** @var Transaction $transaction */
            $transaction = $item['transaction'];
            /** @var Invoice $invoice */
            $invoice = $item['invoice'];

            $invoice->loadMissing(['file.serviceType', 'patient.client']);

            $fileFeeAmount = TaxExportHelpers::resolveFileFeeAmountForFile($invoice->file);
            $clientCountry = TaxExportHelpers::resolveClientCountryFromInvoice($invoice);
            $invoiceAmount = (float) ($invoice->total_amount ?? 0);
            $amount = $fileFeeAmount !== null
                ? round(TaxExportHelpers::resolveAmountBeforeIva($fileFeeAmount, $ivaRate), 2)
                : round($invoiceAmount, 2);
            $totalAfterIva = $fileFeeAmount !== null
                ? round($fileFeeAmount, 2)
                : round($invoiceAmount, 2);

            $rows[] = [
                $transaction->id,
                $transaction->id,
                optional($invoice->invoice_date)->format('Y-m-d'),
                $invoice->name,
                $invoice->file?->serviceType?->name ?? '-',
                $invoice->patient?->client?->company_name ?? '-',
                $clientCountry,
                TaxExportHelpers::resolveNifValue($invoice, $nifSource),
                $invoice->patient?->name ?? '-',
                $amount,
                $ivaPercent,
                $totalAfterIva,
                $invoice->status,
                $this->linkResolver->invoiceLinks($invoice),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    protected function paymentsSummaryHeadings(): array
    {
        return [
            'Group ID',
            'Transaction ID',
            'Payment Type',
            'Date',
            'Party Name',
            'Amount',
            'Bill Count',
            'Bill Numbers',
            'Documentation Status',
            'Trx Out PDF Link',
            'Receipt Link',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function buildPaymentsSummaryRows(Collection $paymentTransactions): array
    {
        $rows = [];

        foreach ($paymentTransactions as $transaction) {
            $hasBills = $transaction->bills->isNotEmpty();
            $paymentType = match (true) {
                $transaction->type === 'Expense' => 'Expense',
                $hasBills => 'Bulk Outflow',
                default => 'Card Payment',
            };

            $rows[] = [
                $transaction->id,
                $transaction->id,
                $paymentType,
                $transaction->date?->format('Y-m-d'),
                $transaction->getRelatedPartyLabel() ?? ($transaction->name ?? '-'),
                round(abs((float) $transaction->amount), 2),
                $transaction->bills->count(),
                $transaction->bills->pluck('name')->implode(', ') ?: '-',
                $this->documentationService->formatDocumentationStatusLabel($transaction->documentation_status),
                $hasBills ? $this->linkResolver->trxOutLink($transaction) : '',
                $hasBills ? '' : $this->linkResolver->transactionReceiptLinks($transaction),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    protected function paymentDetailHeadings(): array
    {
        return [
            'Group ID',
            'Transaction ID',
            'Detail Type',
            'Bill Number',
            'Bill Date',
            'Provider',
            'Patient',
            'Service',
            'Bill Amount',
            'Amount Paid (Pivot)',
            'Client Name',
            'NIF',
            'Bill Attachment Links',
            'Trx Out PDF Link',
            'Receipt Link',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function buildPaymentDetailRows(Collection $paymentTransactions, string $nifSource): array
    {
        $rows = [];

        foreach ($paymentTransactions as $transaction) {
            if ($transaction->type === 'Outflow' && $transaction->bills->isNotEmpty()) {
                foreach ($transaction->bills as $bill) {
                    $bill->loadMissing(['file.patient.client', 'file.serviceType', 'provider']);
                    $file = $bill->file;
                    $billAmount = TaxExportHelpers::resolveBillAmount($bill);

                    $rows[] = [
                        $transaction->id,
                        $transaction->id,
                        'Bill',
                        $bill->name,
                        optional($bill->bill_date)->format('Y-m-d'),
                        $bill->provider?->name ?? '-',
                        $file?->patient?->name ?? '-',
                        $file?->serviceType?->name ?? '-',
                        $billAmount > 0 ? round($billAmount, 2) : 0,
                        round((float) ($bill->pivot->amount_paid ?? 0), 2),
                        $file?->patient?->client?->company_name ?? '-',
                        TaxExportHelpers::resolveNifFromFile($file, $nifSource),
                        $this->linkResolver->billLinks($bill),
                        $this->linkResolver->trxOutLink($transaction),
                        '',
                    ];
                }

                continue;
            }

            $detailType = $transaction->type === 'Expense' ? 'Expense' : 'Card Payment';

            $rows[] = [
                $transaction->id,
                $transaction->id,
                $detailType,
                '-',
                $transaction->date?->format('Y-m-d'),
                $transaction->getRelatedPartyLabel() ?? ($transaction->name ?? '-'),
                '-',
                '-',
                round(abs((float) $transaction->amount), 2),
                round(abs((float) $transaction->amount), 2),
                '-',
                '-',
                '',
                '',
                $this->linkResolver->transactionReceiptLinks($transaction),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    protected function clientsHeadings(): array
    {
        return [
            'Client ID',
            'Company Name',
            'Type',
            'Status',
            'Address',
            'Country',
            'NIF',
            'Email',
            'Phone',
            'Operation Email',
            'Financial Contact Country',
            'Operation Contact Country',
            'GOP Contact Country',
            'Invoice Count In Period',
            'Total Invoiced In Period',
            'Transaction ID(s)',
        ];
    }

    /**
     * @param  Collection<int, array{transaction: Transaction, invoice: Invoice}>  $receivableInvoices
     * @return array<int, array<int, mixed>>
     */
    protected function buildClientsRows(Collection $receivableInvoices): array
    {
        $clientStats = [];

        foreach ($receivableInvoices as $item) {
            $invoice = $item['invoice'];
            $transaction = $item['transaction'];
            $client = $invoice->patient?->client;

            if (! $client) {
                continue;
            }

            $clientId = $client->id;

            if (! isset($clientStats[$clientId])) {
                $clientStats[$clientId] = [
                    'client' => $client,
                    'count' => 0,
                    'total' => 0.0,
                    'transaction_ids' => [],
                ];
            }

            $clientStats[$clientId]['count']++;
            $clientStats[$clientId]['total'] += (float) ($invoice->total_amount ?? 0);
            $clientStats[$clientId]['transaction_ids'][$transaction->id] = $transaction->id;
        }

        $rows = [];

        foreach ($clientStats as $stats) {
            /** @var Client $client */
            $client = $stats['client'];
            $client->loadMissing([
                'country',
                'financialContact.country',
                'operationContact.country',
                'gopContact.country',
            ]);

            $rows[] = [
                $client->id,
                $client->company_name ?? '',
                $client->type ?? '',
                $client->status ?? '',
                $client->address ?? '',
                TaxExportHelpers::resolveClientCountryFromClient($client),
                $client->niv_number ?? '',
                $client->email ?? '',
                $client->phone ?? '',
                $client->operation_email ?? '',
                $client->financialContact?->country?->name ?? '',
                $client->operationContact?->country?->name ?? '',
                $client->gopContact?->country?->name ?? '',
                $stats['count'],
                round($stats['total'], 2),
                implode(', ', array_values($stats['transaction_ids'])),
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp((string) $a[1], (string) $b[1]));

        return $rows;
    }

    protected function internalBankTransactionsQuery($startDate, $endDate): Builder
    {
        return Transaction::query()
            ->whereHas('bankAccount', fn (Builder $query) => $query->where('type', 'Internal'))
            ->whereBetween('date', [$startDate, $endDate]);
    }
}
