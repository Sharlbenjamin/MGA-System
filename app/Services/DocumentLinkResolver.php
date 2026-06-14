<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use Illuminate\Support\Facades\Storage;

class DocumentLinkResolver
{
    public function exportExpiryMinutes(): int
    {
        return config('documents.export_link_expiry_days', 90) * 24 * 60;
    }

    /**
     * @param  array<int, string|null>  $links
     */
    public function joinLinks(array $links): string
    {
        return collect($links)
            ->filter(fn (?string $link) => filled($link))
            ->unique()
            ->implode(' | ');
    }

    public function invoiceLinks(Invoice $invoice, ?int $expirationMinutes = null): string
    {
        $minutes = $expirationMinutes ?? $this->exportExpiryMinutes();
        $links = [];

        if ($invoice->invoice_google_link) {
            $links[] = $this->normalizeExternalUrl($invoice->invoice_google_link);
        }

        $signed = $invoice->getDocumentSignedUrl($minutes);
        if ($signed) {
            $links[] = $signed;
        }

        return $this->joinLinks($links);
    }

    public function billLinks(Bill $bill, ?int $expirationMinutes = null): string
    {
        $minutes = $expirationMinutes ?? $this->exportExpiryMinutes();
        $links = [];

        if ($bill->bill_google_link) {
            $links[] = $this->normalizeExternalUrl($bill->bill_google_link);
        }

        $signed = $bill->getDocumentSignedUrl($minutes);
        if ($signed) {
            $links[] = $signed;
        }

        return $this->joinLinks($links);
    }

    public function transactionAttachmentLinks(Transaction $transaction, ?int $expirationMinutes = null): string
    {
        $minutes = $expirationMinutes ?? $this->exportExpiryMinutes();
        $links = [];

        if ($transaction->attachment_path) {
            if ($this->isExternalPath($transaction->attachment_path)) {
                $links[] = $this->normalizeExternalUrl($transaction->attachment_path);
            } else {
                $signed = $transaction->getDocumentSignedUrl($minutes);
                if ($signed) {
                    $links[] = $signed;
                }
            }
        }

        $transaction->loadMissing('attachments');

        foreach ($transaction->attachments as $attachment) {
            $signed = $this->transactionTypedAttachmentLink($attachment, $minutes);
            if ($signed) {
                $links[] = $signed;
            }
        }

        return $this->joinLinks($links);
    }

    public function transactionAllLinks(Transaction $transaction, ?int $expirationMinutes = null): string
    {
        $minutes = $expirationMinutes ?? $this->exportExpiryMinutes();
        $links = [];

        $attachmentLinks = $this->transactionAttachmentLinks($transaction, $minutes);
        if ($attachmentLinks !== '') {
            $links[] = $attachmentLinks;
        }

        if ($transaction->type === 'Income') {
            $transaction->loadMissing('invoices');
            foreach ($transaction->invoices as $invoice) {
                $invoiceLink = $this->invoiceLinks($invoice, $minutes);
                if ($invoiceLink !== '') {
                    $links[] = $invoiceLink;
                }
            }

            $trxIn = $transaction->getTrxInPdfUrl($minutes);
            if ($trxIn) {
                $links[] = $trxIn;
            }
        }

        if ($transaction->type === 'Outflow' && $transaction->bills()->exists()) {
            $transaction->loadMissing('bills');
            foreach ($transaction->bills as $bill) {
                $billLink = $this->billLinks($bill, $minutes);
                if ($billLink !== '') {
                    $links[] = $billLink;
                }
            }

            $trxOut = $transaction->getTrxOutPdfUrl($minutes);
            if ($trxOut) {
                $links[] = $trxOut;
            }
        }

        return $this->joinLinks($links);
    }

    public function transactionTypedAttachmentLink(TransactionAttachment $attachment, ?int $expirationMinutes = null): ?string
    {
        if (! $attachment->file_path) {
            return null;
        }

        if ($this->isExternalPath($attachment->file_path)) {
            return $this->normalizeExternalUrl($attachment->file_path);
        }

        if (! Storage::disk('public')->exists($attachment->file_path)) {
            return null;
        }

        $minutes = $expirationMinutes ?? $this->exportExpiryMinutes();

        return $attachment->getDocumentSignedUrl($minutes);
    }

    public function isExternalPath(string $path): bool
    {
        return str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_contains($path, 'drive.google.com')
            || str_contains($path, '://')
            || str_starts_with($path, 'www.');
    }

    public function normalizeExternalUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return 'https://' . ltrim($url, '/');
    }
}
