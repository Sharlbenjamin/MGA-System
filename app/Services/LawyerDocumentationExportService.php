<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        $invoices = $this->paidInvoicesQuery($startDate, $endDate)->get();
        $paidInvoiceFileIds = $invoices->pluck('file_id')->filter()->unique()->values();

        $filenameQuarter = $quarter === 'full' ? 'full' : 'Q' . $quarter;
        $filename = "lawyer_documentation_{$year}_{$filenameQuarter}_" . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return [
            'filename' => $filename,
            'transfers' => [
                'headings' => $this->transfersHeadings(),
                'rows' => $this->buildTransfersRows($startDate, $endDate),
            ],
            'payables' => [
                'headings' => $this->payablesHeadings(),
                'rows' => $this->buildPayablesRows($startDate, $endDate, $paidInvoiceFileIds, $nifSource),
            ],
            'receivables' => [
                'headings' => $this->receivablesHeadings(),
                'rows' => $this->buildReceivablesRows($invoices, $ivaPercent, $ivaRate, $nifSource),
            ],
            'clients' => [
                'headings' => $this->clientsHeadings(),
                'rows' => $this->buildClientsRows($invoices),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function transfersHeadings(): array
    {
        return [
            'Transaction ID',
            'Date',
            'Type',
            'Category',
            'Direction',
            'Amount',
            'Reference',
            'Description',
            'Related Party',
            'Linked Invoices',
            'Linked Bills',
            'Documentation Status',
            'Documentation Notes',
            'Attachment Links',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function buildTransfersRows($startDate, $endDate): array
    {
        $rows = [];

        $this->internalBankTransactionsQuery($startDate, $endDate)
            ->with(['invoices', 'bills', 'attachments', 'bankAccount'])
            ->orderBy('date')
            ->orderBy('id')
            ->chunk(100, function ($transactions) use (&$rows) {
                foreach ($transactions as $transaction) {
                    $reference = $transaction->reference ?: $transaction->name;
                    $description = $transaction->notes ?: $transaction->name ?: $reference;

                    $rows[] = [
                        $transaction->id,
                        $transaction->date?->format('Y-m-d'),
                        $transaction->type,
                        $this->documentationService->getDocumentationLabel($transaction),
                        $this->documentationService->getDirection($transaction),
                        round(abs((float) $transaction->amount), 2),
                        $reference,
                        $description,
                        $transaction->getRelatedPartyLabel() ?? '-',
                        $transaction->invoices->pluck('name')->implode(', ') ?: '-',
                        $transaction->bills->pluck('name')->implode(', ') ?: '-',
                        $this->documentationService->formatDocumentationStatusLabel($transaction->documentation_status),
                        $this->documentationService->getPendingTaskSummary($transaction) ?? '',
                        $this->linkResolver->transactionAllLinks($transaction),
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    protected function payablesHeadings(): array
    {
        return [
            'Payable Type',
            'Date',
            'Document Number',
            'Service',
            'Party Name',
            'Client Country',
            'NIF',
            'Patient Name',
            'Amount',
            'Status',
            'Source',
            'Notes',
            'Attachment Links',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function buildPayablesRows($startDate, $endDate, Collection $paidInvoiceFileIds, string $nifSource): array
    {
        $rows = [];

        $bills = Bill::query()
            ->with([
                'file.serviceType',
                'file.providerBranch.provider',
                'provider',
                'file.patient.client.financialContact.country',
                'file.patient.client.operationContact.country',
                'file.patient.client.gopContact.country',
                'file.patient.client.country',
            ])
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->whereIn('file_id', $paidInvoiceFileIds)
            ->orderBy('bill_date')
            ->get();

        foreach ($bills as $bill) {
            $amount = TaxExportHelpers::resolveBillAmount($bill);
            $exportBillAmount = $amount > 0 ? round($amount, 2) : 0;
            $file = $bill->file;
            $clientCountry = TaxExportHelpers::resolveClientCountryFromFile($file);

            $rows[] = [
                'Bill',
                optional($bill->bill_date)->format('Y-m-d'),
                $bill->name,
                $file?->serviceType?->name ?? '-',
                $bill->provider?->name
                    ?? $file?->providerBranch?->provider?->name
                    ?? '-',
                $clientCountry,
                TaxExportHelpers::resolveNifFromFile($file, $nifSource),
                $file?->patient?->name ?? '-',
                $exportBillAmount,
                $bill->status ?? 'N/A',
                'Bill',
                '',
                $this->linkResolver->billLinks($bill),
            ];
        }

        $this->internalBankTransactionsQuery($startDate, $endDate)
            ->where('type', 'Outflow')
            ->with(['bills.file.patient', 'bills.file.serviceType', 'attachments'])
            ->orderBy('date')
            ->orderBy('id')
            ->chunk(100, function ($transactions) use (&$rows, $nifSource) {
                foreach ($transactions as $transaction) {
                    $hasBills = $transaction->bills->isNotEmpty();
                    $payableType = $hasBills ? 'Bulk Bill' : 'Card Payment';
                    $firstBill = $transaction->bills->first();
                    $file = $firstBill?->file;

                    $rows[] = [
                        $payableType,
                        $transaction->date?->format('Y-m-d'),
                        $transaction->name,
                        $file?->serviceType?->name ?? '-',
                        $transaction->getRelatedPartyLabel() ?? ($transaction->name ?? '-'),
                        $file ? TaxExportHelpers::resolveClientCountryFromFile($file) : '-',
                        $file ? TaxExportHelpers::resolveNifFromFile($file, $nifSource) : '-',
                        $file?->patient?->name ?? '-',
                        round(abs((float) $transaction->amount), 2),
                        $transaction->status ?? 'N/A',
                        'Transaction',
                        $transaction->notes ?? '',
                        $this->linkResolver->transactionAllLinks($transaction),
                    ];
                }
            });

        $expenses = DB::table('transactions')
            ->join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
            ->where('transactions.type', 'Expense')
            ->where('bank_accounts.type', 'Internal')
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->orderBy('transactions.date')
            ->select('transactions.*')
            ->get();

        foreach ($expenses as $expense) {
            $transaction = Transaction::with('attachments')->find($expense->id);
            if (! $transaction) {
                continue;
            }

            $rows[] = [
                'Expense',
                optional($transaction->date)->format('Y-m-d'),
                $transaction->name,
                '-',
                $transaction->name ?? '-',
                '-',
                '-',
                '-',
                round(abs((float) $transaction->amount), 2),
                $transaction->status ?? 'Expense',
                'Transaction',
                $transaction->notes ?? '',
                $this->linkResolver->transactionAttachmentLinks($transaction),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    protected function receivablesHeadings(): array
    {
        return [
            'Record Type',
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
            'Source',
            'Notes',
            'Linked Transaction ID(s)',
            'Attachment Links',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function buildReceivablesRows(Collection $invoices, float $ivaPercent, float $ivaRate, string $nifSource): array
    {
        $rows = [];

        $invoices->loadMissing(['file.serviceType', 'patient.client', 'transactions']);

        foreach ($invoices as $invoice) {
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
                'Invoice',
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
                'Invoice',
                '',
                $invoice->transactions->pluck('id')->implode(', ') ?: '-',
                $this->linkResolver->invoiceLinks($invoice),
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
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function buildClientsRows(Collection $invoices): array
    {
        $clientStats = [];

        foreach ($invoices as $invoice) {
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
                ];
            }

            $clientStats[$clientId]['count']++;
            $clientStats[$clientId]['total'] += (float) ($invoice->total_amount ?? 0);
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
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp((string) $a[1], (string) $b[1]));

        return $rows;
    }

    protected function paidInvoicesQuery($startDate, $endDate): Builder
    {
        return Invoice::query()
            ->with([
                'file.serviceType',
                'patient.country',
                'patient.client.financialContact.country',
                'patient.client.operationContact.country',
                'patient.client.gopContact.country',
                'patient.client.country',
                'transactions',
            ])
            ->where('status', 'Paid')
            ->where(function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('payment_date', [$startDate, $endDate])
                    ->orWhere(function (Builder $fallbackQuery) use ($startDate, $endDate) {
                        $fallbackQuery->whereNull('payment_date')
                            ->whereBetween('invoice_date', [$startDate, $endDate]);
                    });
            })
            ->orderByRaw('COALESCE(payment_date, invoice_date)');
    }

    protected function internalBankTransactionsQuery($startDate, $endDate): Builder
    {
        return Transaction::query()
            ->whereHas('bankAccount', fn (Builder $query) => $query->where('type', 'Internal'))
            ->whereBetween('date', [$startDate, $endDate]);
    }
}
