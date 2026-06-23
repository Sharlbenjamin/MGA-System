<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\BankAccountResource;
use App\Filament\Resources\TransactionResource;
use App\Filament\Support\TransactionBillLinkForm;
use App\Filament\Support\TransactionInvoiceLinkForm;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionDocumentationStatsService;
use App\Services\TransactionSettlementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    /** @var array<int, array{bill_id: int, amount_paid: float}> */
    protected array $billLinksToAttach = [];

    /** @var array<int, array{invoice_id: int, amount_paid: float}> */
    protected array $invoiceLinksToAttach = [];

    protected ?string $documentationCategory = null;

    protected bool $isDraftPayment = false;

    public function getBreadcrumbs(): array
    {
        $bankAccountId = request()->integer('bank_account_id');

        $breadcrumbs = [
            BankAccountResource::getUrl('index') => BankAccountResource::getBreadcrumb(),
        ];

        if ($bankAccountId) {
            $breadcrumbs[TransactionResource::indexUrlFor($bankAccountId)] = 'Bank Transactions';
        }

        $breadcrumbs['#'] = $this->getTitle();

        return $breadcrumbs;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->billLinksToAttach = $this->normalizeBillLinks($data['bill_links'] ?? []);
        $this->invoiceLinksToAttach = $this->normalizeInvoiceLinks($data['invoice_links'] ?? []);
        $this->documentationCategory = $data['documentation_category'] ?? request()->get('documentation_category');

        $billIds = array_values(array_unique(
            array_map('intval', array_column($data['bill_links'] ?? [], 'bill_id')),
        ));

        if (
            ($data['type'] ?? null) === 'Outflow'
            && in_array($data['related_type'] ?? null, ['Provider', 'Branch'], true)
        ) {
            $autoCategory = TransactionDocumentationStatsService::providerBillCategoryForCount(count($billIds));
            $currentCategory = $data['documentation_category'] ?? $this->documentationCategory;

            if ($autoCategory && TransactionDocumentationStatsService::shouldAutoAdjustProviderBillCategory($currentCategory)) {
                $data['documentation_category'] = $autoCategory;
                $this->documentationCategory = $autoCategory;
            }
        }

        unset($data['bill_links'], $data['invoice_links']);

        if (blank($data['documentation_category'] ?? null)) {
            $data['documentation_category'] = TransactionDocumentationStatsService::defaultCategoryFor(
                $data['type'] ?? null,
                $data['related_type'] ?? null,
            );
        }

        $requestedStatus = request()->get('status') ?? $data['status'] ?? 'Completed';
        $data['status'] = $requestedStatus === 'Draft' ? 'Draft' : ($data['status'] ?? 'Completed');
        $this->isDraftPayment = $data['status'] === 'Draft';

        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();
        $data['documentation_status'] = 'incomplete';

        return $data;
    }

    protected function afterCreate(): void
    {
        $categoryWarning = null;

        TransactionDocumentationService::withoutObserverSync(function () use (&$categoryWarning): void {
            $transaction = $this->record->fresh();
            $statsService = app(TransactionDocumentationStatsService::class);

            $this->billLinksToAttach = $this->mergeBillLinksFromRequest($this->billLinksToAttach);
            $this->invoiceLinksToAttach = $this->mergeInvoiceLinksFromRequest($this->invoiceLinksToAttach);

            $billIds = array_values(array_unique(array_column($this->billLinksToAttach, 'bill_id')));

            if ($this->billLinksToAttach !== []) {
                foreach ($this->billLinksToAttach as $link) {
                    TransactionBillLinkForm::attachBill(
                        $transaction,
                        $link['bill_id'],
                        $link['amount_paid'],
                        notify: false,
                        sync: false,
                    );
                }
            } elseif ($billIds !== [] && in_array($transaction->related_type, ['Provider', 'Branch'], true)) {
                if ($this->isDraftPayment) {
                    $transaction->attachBillsForDraft($billIds);
                } else {
                    $statsService->syncBills($transaction, $billIds);
                }
            }

            $transaction = $transaction->fresh();
            $linkedBillIds = $transaction->bills()->pluck('bills.id')->all();

            if ($this->documentationCategory) {
                try {
                    $statsService->applyCategory(
                        $transaction,
                        $this->documentationCategory,
                        $linkedBillIds,
                    );
                } catch (ValidationException $exception) {
                    $categoryWarning = collect($exception->errors())->flatten()->first()
                        ?? 'Category could not be applied.';
                }
            }

            if ($transaction->related_type === 'Client' && $this->invoiceLinksToAttach !== []) {
                TransactionInvoiceLinkForm::attachLinksFromCreate($transaction, $this->invoiceLinksToAttach);
            }

            $statsService->applyProviderBillCategoryFromCount($transaction->fresh());
        });

        app(TransactionSettlementService::class)->syncDocumentation($this->record->fresh());

        if ($categoryWarning) {
            Notification::make()
                ->warning()
                ->title('Bills linked, but category needs attention')
                ->body($categoryWarning)
                ->persistent()
                ->send();
        }

        if ($this->isDraftPayment) {
            Notification::make()
                ->title('Draft payment created')
                ->body('Bills are linked but not marked paid until you confirm the bank statement and finalize.')
                ->info()
                ->send();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     * @return array<int, array{bill_id: int, amount_paid: float}>
     */
    protected function normalizeBillLinks(array $links): array
    {
        $normalized = [];

        foreach ($links as $link) {
            $billId = (int) ($link['bill_id'] ?? 0);

            if (! $billId) {
                continue;
            }

            $normalized[] = [
                'bill_id' => $billId,
                'amount_paid' => (float) ($link['amount_paid'] ?? 0),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     * @return array<int, array{invoice_id: int, amount_paid: float}>
     */
    protected function normalizeInvoiceLinks(array $links): array
    {
        $normalized = [];

        foreach ($links as $link) {
            $invoiceId = (int) ($link['invoice_id'] ?? 0);

            if (! $invoiceId) {
                continue;
            }

            $normalized[] = [
                'invoice_id' => $invoiceId,
                'amount_paid' => (float) ($link['amount_paid'] ?? 0),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{bill_id: int, amount_paid: float}>  $links
     * @return array<int, array{bill_id: int, amount_paid: float}>
     */
    protected function mergeBillLinksFromRequest(array $links): array
    {
        $billId = request()->integer('bill_id');

        if ($billId && collect($links)->doesntContain('bill_id', $billId)) {
            $links[] = ['bill_id' => $billId, 'amount_paid' => 0];
        }

        if ($links === [] && request()->get('bill_ids')) {
            foreach (array_filter(array_map('intval', explode(',', (string) request()->get('bill_ids')))) as $id) {
                $links[] = ['bill_id' => $id, 'amount_paid' => 0];
            }
        }

        return $links;
    }

    /**
     * @param  array<int, array{invoice_id: int, amount_paid: float}>  $links
     * @return array<int, array{invoice_id: int, amount_paid: float}>
     */
    protected function mergeInvoiceLinksFromRequest(array $links): array
    {
        $invoiceId = request()->integer('invoice_id');

        if ($invoiceId && collect($links)->doesntContain('invoice_id', $invoiceId)) {
            $links[] = ['invoice_id' => $invoiceId, 'amount_paid' => 0];
        }

        return $links;
    }
}
