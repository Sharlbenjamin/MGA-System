<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\BankAccountResource;
use App\Filament\Resources\TransactionResource;
use App\Filament\Support\TransactionInvoiceLinkForm;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionDocumentationStatsService;
use App\Services\TransactionSettlementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    /** @var array<int, int> */
    protected array $billsToAttach = [];

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
        $this->billsToAttach = TransactionDocumentationStatsService::normalizeLinkIds($data['bills'] ?? []);
        $this->invoiceLinksToAttach = $this->normalizeInvoiceLinks($data['invoice_links'] ?? []);
        $this->documentationCategory = $data['documentation_category'] ?? request()->get('documentation_category');

        unset($data['bills'], $data['invoice_links']);

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
        TransactionDocumentationService::withoutObserverSync(function (): void {
            $transaction = $this->record->fresh();
            $statsService = app(TransactionDocumentationStatsService::class);

            $this->billsToAttach = $this->mergeBillIdsFromRequest($this->billsToAttach);
            $this->invoiceLinksToAttach = $this->mergeInvoiceLinksFromRequest($this->invoiceLinksToAttach);

            if ($this->documentationCategory) {
                $statsService->applyCategory(
                    $transaction,
                    $this->documentationCategory,
                    $this->billsToAttach,
                );
            } elseif (in_array($transaction->related_type, ['Provider', 'Branch'], true)) {
                if ($this->isDraftPayment && $this->billsToAttach !== []) {
                    $transaction->attachBillsForDraft($this->billsToAttach);
                } else {
                    $statsService->syncBills($transaction, $this->billsToAttach);
                }
            }

            if ($transaction->related_type === 'Client' && $this->invoiceLinksToAttach !== []) {
                TransactionInvoiceLinkForm::attachLinksFromCreate($transaction, $this->invoiceLinksToAttach);
            }
        });

        app(TransactionSettlementService::class)->syncDocumentation($this->record->fresh());

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
     * @param  array<int, int>  $billIds
     * @return array<int, int>
     */
    protected function mergeBillIdsFromRequest(array $billIds): array
    {
        $merged = $billIds;

        $billId = request()->integer('bill_id');
        if ($billId) {
            $merged[] = $billId;
        }

        if ($billIds === [] && request()->get('bill_ids')) {
            $merged = array_merge(
                $merged,
                array_values(array_filter(array_map('intval', explode(',', (string) request()->get('bill_ids'))))),
            );
        }

        return array_values(array_unique(array_filter($merged)));
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
