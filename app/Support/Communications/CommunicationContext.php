<?php

namespace App\Support\Communications;

use App\Enums\CommunicationContextType;
use App\Models\Bill;
use App\Models\File;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

class CommunicationContext
{
    /**
     * @param  array<int, File>  $cases
     * @param  array<int, Bill>  $bills
     * @param  array<int, Transaction>  $transactions
     * @param  array<int, array<int, string>>  $missingDocumentsByCase  file_id => document labels
     */
    public function __construct(
        public readonly CommunicationContextType $contextType,
        public readonly ?Provider $provider = null,
        public readonly ?ProviderBranch $providerBranch = null,
        public readonly ?File $case = null,
        public readonly ?Patient $patient = null,
        public readonly ?User $currentUser = null,
        public readonly array $cases = [],
        public readonly array $bills = [],
        public readonly array $transactions = [],
        public readonly array $missingDocumentsByCase = [],
        public readonly array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toTemplateData(): array
    {
        $case = $this->case ?? ($this->cases[0] ?? null);
        $patient = $this->patient ?? $case?->patient;

        return [
            'provider' => [
                'name' => $this->provider?->name ?? '',
            ],
            'provider_branch' => [
                'name' => $this->providerBranch?->branch_name ?? $this->providerBranch?->name ?? '',
                'phone' => $this->providerBranch?->phone ?? '',
                'email' => $this->providerBranch?->email ?? '',
            ],
            'case' => [
                'reference' => $case?->mga_reference ?? '',
                'client_reference' => $case?->client_reference ?? '',
                'id' => $case?->id ?? '',
            ],
            'patient' => [
                'name' => $patient?->name ?? '',
                'reference' => $patient?->id ?? '',
                'dob' => $patient?->dob ? Carbon::parse($patient->dob)->format('d-m-Y') : '',
                'gender' => $patient?->gender ?? '',
                'address' => $case?->address ?? '',
                'phone' => $case?->phone ?? '',
            ],
            'appointment' => [
                'date' => $case?->service_date ? Carbon::parse($case->service_date)->format('d-m-Y') : '',
                'time' => $case?->service_time ? Carbon::parse($case->service_time)->format('H:i') : '',
                'service' => $case?->serviceType?->name ?? '',
                'symptoms' => $case?->symptoms ?? '',
                'notes' => $this->extra['appointment_notes'] ?? '',
            ],
            'coverage' => [
                'amount' => $this->extra['coverage_amount'] ?? '',
            ],
            'user' => [
                'name' => $this->currentUser?->name ?? '',
                'signature' => $this->currentUser?->signature?->name ?? $this->currentUser?->name ?? '',
            ],
            'cases_table' => $this->extra['cases_table'] ?? $this->buildCasesTable(),
            'missing_files_table' => $this->extra['missing_files_table'] ?? $this->buildMissingFilesTable(),
            'bills_table' => $this->extra['bills_table'] ?? $this->buildBillsTable(),
            'transactions_table' => $this->extra['transactions_table'] ?? $this->buildTransactionsTable(),
            'transaction' => [
                'reference' => $this->transactions[0]?->reference ?? $this->transactions[0]?->name ?? '',
                'amount' => isset($this->transactions[0]) ? number_format((float) $this->transactions[0]->amount, 2) : '',
                'date' => $this->transactions[0]?->date?->format('d-m-Y') ?? '',
                'currency' => 'EUR',
            ],
        ];
    }

    protected function buildCasesTable(): string
    {
        if ($this->cases === []) {
            return '';
        }

        $lines = [];
        foreach ($this->cases as $file) {
            $lines[] = '- '.($file->mga_reference ?? 'Case #'.$file->id);
        }

        return implode("\n", $lines);
    }

    protected function buildMissingFilesTable(): string
    {
        if ($this->missingDocumentsByCase === []) {
            return '';
        }

        $lines = [];
        foreach ($this->missingDocumentsByCase as $fileId => $documents) {
            $file = collect($this->cases)->firstWhere('id', $fileId)
                ?? File::query()->find($fileId);
            $ref = $file?->mga_reference ?? 'Case #'.$fileId;
            $lines[] = $ref;
            foreach ($documents as $document) {
                $lines[] = '- '.$document;
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    protected function buildBillsTable(): string
    {
        if ($this->bills === []) {
            return '';
        }

        $lines = [];
        foreach ($this->bills as $bill) {
            $bill->loadMissing('file');
            $caseRef = $bill->file?->mga_reference ?? 'N/A';
            $amount = number_format((float) $bill->total_amount, 2);
            $date = $bill->bill_date?->format('d-m-Y') ?? 'N/A';
            $lines[] = sprintf(
                '- %s | Case %s | %s EUR | %s | Status: %s',
                $bill->display_name ?? $bill->name,
                $caseRef,
                $amount,
                $date,
                $bill->status ?? 'N/A',
            );
        }

        return implode("\n", $lines);
    }

    protected function buildTransactionsTable(): string
    {
        if ($this->transactions === []) {
            return '';
        }

        $lines = [];
        foreach ($this->transactions as $transaction) {
            $transaction->loadMissing(['bills.file']);
            $ref = $transaction->reference ?? $transaction->name ?? 'TRX-'.$transaction->id;
            $amount = number_format((float) $transaction->amount, 2);
            $date = $transaction->date?->format('d-m-Y') ?? 'N/A';
            $lines[] = sprintf('- %s | %s EUR | %s', $ref, $amount, $date);

            foreach ($transaction->bills as $bill) {
                $caseRef = $bill->file?->mga_reference ?? 'N/A';
                $lines[] = '  • Case '.$caseRef.' — '.($bill->display_name ?? $bill->name);
            }
        }

        return implode("\n", $lines);
    }
}
