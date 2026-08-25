<?php

namespace App\Services\Communications;

use App\Enums\CommunicationContextType;
use App\Models\Bill;
use App\Models\File;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FileWorkflowGapService;
use App\Support\Communications\CommunicationContext;
use Illuminate\Support\Collection;

class CommunicationContextFactory
{
    public function forAppointmentRequest(
        File $file,
        ProviderBranch $branch,
        ?User $user = null,
    ): CommunicationContext {
        $file->loadMissing(['patient', 'serviceType', 'gops']);
        $branch->loadMissing('provider');

        $outGop = $file->gops->where('type', 'Out')->sortByDesc('date')->first();

        return new CommunicationContext(
            contextType: CommunicationContextType::AppointmentRequest,
            provider: $branch->provider,
            providerBranch: $branch,
            case: $file,
            patient: $file->patient,
            currentUser: $user,
            cases: [$file],
            extra: [
                'coverage_amount' => $outGop ? number_format((float) $outGop->amount, 0).'€' : '',
            ],
        );
    }

    /**
     * @param  Collection<int, File>|array<int, File>  $files
     * @param  array<int, string>  $gapTypes  FileWorkflowGapService constants or custom labels
     */
    public function forMissingDocuments(
        Provider $provider,
        Collection|array $files,
        ?ProviderBranch $branch = null,
        ?User $user = null,
        array $gapTypes = [],
    ): CommunicationContext {
        $files = collect($files);
        $files->each(fn (File $file) => $file->loadMissing(['patient', 'gops', 'medicalReports', 'bills', 'prescriptions']));

        $missingByCase = [];

        foreach ($files as $file) {
            $documents = $this->resolveMissingDocumentLabels($file, $gapTypes);
            if ($documents !== []) {
                $missingByCase[$file->id] = $documents;
            }
        }

        return new CommunicationContext(
            contextType: CommunicationContextType::MissingDocuments,
            provider: $provider,
            providerBranch: $branch,
            case: $files->first(),
            patient: $files->first()?->patient,
            currentUser: $user,
            cases: $files->all(),
            missingDocumentsByCase: $missingByCase,
        );
    }

    /**
     * @param  Collection<int, File>|array<int, File>  $files
     */
    public function forMissingBills(
        Provider $provider,
        Collection|array $files,
        ?ProviderBranch $branch = null,
        ?User $user = null,
    ): CommunicationContext {
        $files = collect($files);
        $files->each(fn (File $file) => $file->loadMissing('patient'));

        return new CommunicationContext(
            contextType: CommunicationContextType::MissingBills,
            provider: $provider,
            providerBranch: $branch,
            case: $files->first(),
            patient: $files->first()?->patient,
            currentUser: $user,
            cases: $files->all(),
        );
    }

    /**
     * @param  Collection<int, Bill>|array<int, Bill>  $bills
     */
    public function forOutstandingBills(
        Provider $provider,
        Collection|array $bills,
        ?ProviderBranch $branch = null,
        ?User $user = null,
    ): CommunicationContext {
        $bills = collect($bills);
        $bills->each(fn (Bill $bill) => $bill->loadMissing(['file.patient']));

        $cases = $bills->pluck('file')->filter()->unique('id')->values()->all();

        return new CommunicationContext(
            contextType: CommunicationContextType::OutstandingBillsAcknowledgement,
            provider: $provider,
            providerBranch: $branch,
            case: $cases[0] ?? null,
            patient: $cases[0]?->patient ?? null,
            currentUser: $user,
            cases: $cases,
            bills: $bills->all(),
        );
    }

    /**
     * @param  Collection<int, Transaction>|array<int, Transaction>  $transactions
     */
    public function forTransactions(
        Provider $provider,
        Collection|array $transactions,
        ?ProviderBranch $branch = null,
        ?User $user = null,
    ): CommunicationContext {
        $transactions = collect($transactions);
        $transactions->each(fn (Transaction $transaction) => $transaction->loadMissing(['bills.file']));

        $cases = $transactions
            ->flatMap(fn (Transaction $transaction) => $transaction->bills->pluck('file'))
            ->filter()
            ->unique('id')
            ->values()
            ->all();

        return new CommunicationContext(
            contextType: CommunicationContextType::TransactionNotification,
            provider: $provider,
            providerBranch: $branch,
            case: $cases[0] ?? null,
            patient: $cases[0]?->patient ?? null,
            currentUser: $user,
            cases: $cases,
            transactions: $transactions->all(),
        );
    }

    /**
     * @param  array<int, string>  $gapTypes
     * @return array<int, string>
     */
    public function resolveMissingDocumentLabels(File $file, array $gapTypes = []): array
    {
        $labels = [];

        $checks = [
            FileWorkflowGapService::GAP_GOP => fn () => FileWorkflowGapService::missingGop($file) ? ['GOP'] : [],
            FileWorkflowGapService::GAP_GOP_DOC => fn () => FileWorkflowGapService::missingGopDoc($file) ? ['GOP Document'] : [],
            FileWorkflowGapService::GAP_MR => fn () => FileWorkflowGapService::missingMr($file) ? ['Medical Report'] : [],
            FileWorkflowGapService::GAP_BILL => fn () => FileWorkflowGapService::missingBill($file) ? ['Bill / Invoice'] : [],
        ];

        $selected = $gapTypes === [] ? array_keys($checks) : $gapTypes;

        foreach ($selected as $gapType) {
            if (isset($checks[$gapType])) {
                $labels = array_merge($labels, $checks[$gapType]());
            }
        }

        foreach ($file->prescriptions ?? [] as $prescription) {
            if (blank($prescription->document_path)) {
                $labels[] = 'Prescription';
            }
        }

        foreach ($file->gops ?? [] as $gop) {
            if ($gop->type === 'In' && blank($gop->gop_google_drive_link) && blank($gop->document_path)) {
                if (! in_array('GOP Document', $labels, true)) {
                    $labels[] = 'GOP Document';
                }
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @return array<string, string>
     */
    public static function missingDocumentGapOptions(): array
    {
        return [
            FileWorkflowGapService::GAP_GOP => 'Missing GOP',
            FileWorkflowGapService::GAP_GOP_DOC => 'Missing GOP document',
            FileWorkflowGapService::GAP_MR => 'Missing medical report',
            FileWorkflowGapService::GAP_BILL => 'Missing bill / invoice',
        ];
    }
}
