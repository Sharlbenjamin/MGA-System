<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class TransactionDocumentationStatsService
{
    public function breakdown(Builder $query): array
    {
        return [
            'trx_in' => [
                'total' => $this->totalTrxIn($query),
                'with_pdf' => $this->trxInWithPdf($query),
                'without_pdf' => $this->trxInWithoutPdf($query),
            ],
            'trx_out' => [
                'total_incomplete' => $this->totalTrxOutIncomplete($query),
                'bulk_without_pdf' => $this->bulkOutWithoutPdf($query),
                'card_without_attachment' => $this->cardWithoutAttachment($query),
                'single_bill_without_pdf' => $this->singleBillWithoutBillPdf($query),
                'expense_without_attachment' => $this->expenseWithoutReceipt($query),
            ],
        ];
    }

    protected function totalTrxIn(Builder $query): int
    {
        return (clone $query)->where('type', 'Income')->count();
    }

    protected function trxInWithPdf(Builder $query): int
    {
        return (clone $query)
            ->where('type', 'Income')
            ->whereNotNull('trx_in_pdf_path')
            ->count();
    }

    protected function trxInWithoutPdf(Builder $query): int
    {
        return (clone $query)
            ->where('type', 'Income')
            ->whereNull('trx_in_pdf_path')
            ->count();
    }

    protected function totalTrxOutIncomplete(Builder $query): int
    {
        return (clone $query)
            ->whereIn('type', ['Outflow', 'Expense'])
            ->where('documentation_status', '!=', 'complete')
            ->count();
    }

    protected function bulkOutWithoutPdf(Builder $query): int
    {
        return (clone $query)
            ->where('type', 'Outflow')
            ->whereNull('trx_out_pdf_path')
            ->has('bills', '>=', 2)
            ->count();
    }

    protected function cardWithoutAttachment(Builder $query): int
    {
        return (clone $query)
            ->where('type', 'Outflow')
            ->doesntHave('bills')
            ->whereNull('attachment_path')
            ->whereDoesntHave('attachments', fn (Builder $attachmentQuery) => $attachmentQuery->where('type', 'card_receipt'))
            ->count();
    }

    protected function singleBillWithoutBillPdf(Builder $query): int
    {
        return (clone $query)
            ->where('type', 'Outflow')
            ->has('bills', '=', 1)
            ->whereHas('bills', function (Builder $billQuery): void {
                $billQuery
                    ->whereNull('bill_document_path')
                    ->whereNull('bill_google_link');
            })
            ->count();
    }

    protected function expenseWithoutReceipt(Builder $query): int
    {
        return (clone $query)
            ->where('type', 'Expense')
            ->whereNull('attachment_path')
            ->whereDoesntHave('attachments', fn (Builder $attachmentQuery) => $attachmentQuery->where('type', 'expense_receipt'))
            ->count();
    }
}
