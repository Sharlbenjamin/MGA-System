<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\BankAccountResource;
use App\Filament\Resources\TransactionResource;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionDocumentationStatsService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    /** @var array<int, int> */
    protected array $billsToAttach = [];

    /** @var array<int, int> */
    protected array $invoicesToAttach = [];

    protected ?string $documentationCategory = null;

    protected bool $markAsRevised = false;

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
        $bankAccountId = $this->record->bank_account_id ?? request()->integer('bank_account_id');

        if ($bankAccountId) {
            return TransactionResource::indexUrlFor($bankAccountId);
        }

        return BankAccountResource::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->billsToAttach = TransactionDocumentationStatsService::normalizeLinkIds($data['bills'] ?? []);
        $this->invoicesToAttach = TransactionDocumentationStatsService::normalizeLinkIds($data['invoices'] ?? []);
        $this->documentationCategory = $data['documentation_category'] ?? null;
        $this->markAsRevised = ! empty($data['mark_as_revised']);

        unset($data['bills'], $data['invoices'], $data['documentation_category'], $data['mark_as_revised']);

        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();
        $data['documentation_status'] = $this->markAsRevised ? 'revised' : ($data['documentation_status'] ?? 'incomplete');

        return $data;
    }

    protected function afterCreate(): void
    {
        $transaction = $this->record->fresh();
        $statsService = app(TransactionDocumentationStatsService::class);

        $this->billsToAttach = $this->mergeBillIdsFromRequest($this->billsToAttach);
        $this->invoicesToAttach = $this->mergeInvoiceIdsFromRequest($this->invoicesToAttach);

        if ($this->documentationCategory) {
            $statsService->applyCategory(
                $transaction,
                $this->documentationCategory,
                $this->billsToAttach,
            );
            $transaction = $transaction->fresh();
        } elseif (in_array($transaction->related_type, ['Provider', 'Branch'], true)) {
            $statsService->syncBills($transaction, $this->billsToAttach);
        }

        if ($transaction->related_type === 'Client') {
            $statsService->syncInvoices($transaction, $this->invoicesToAttach);
        }

        if (! $this->markAsRevised) {
            app(TransactionDocumentationService::class)->syncAndRecalculate($transaction->fresh());
        }
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
     * @param  array<int, int>  $invoiceIds
     * @return array<int, int>
     */
    protected function mergeInvoiceIdsFromRequest(array $invoiceIds): array
    {
        $merged = $invoiceIds;

        $invoiceId = request()->integer('invoice_id');
        if ($invoiceId) {
            $merged[] = $invoiceId;
        }

        return array_values(array_unique(array_filter($merged)));
    }
}
