<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Transaction extends Model
{
    protected $fillable = [
        'name',
        'bank_account_id',
        'related_type',
        'related_id',
        'amount',
        'type',
        'date',
        'notes',
        'attachment_path',
        'bank_charges',
        'charges_covered_by_client',
        'status',
        'reference',
        'documentation_status',
        'documentation_category',
        'trx_out_pdf_path',
        'trx_in_pdf_path',
        'import_batch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'charges_covered_by_client' => 'boolean',
        'bank_charges' => 'decimal:2',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(TransactionAttachment::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(TransactionImportBatch::class, 'import_batch_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getDirectionAttribute(): string
    {
        return app(\App\Services\TransactionDocumentationService::class)->getDirection($this);
    }

    public function getDocumentationLabelAttribute(): string
    {
        return app(\App\Services\TransactionDocumentationService::class)->getDocumentationLabel($this);
    }

    public function getPendingDocumentationCountAttribute(): int
    {
        return app(\App\Services\TransactionDocumentationService::class)->getPendingTaskCount($this);
    }

    public function getTrxInPdfUrl(int $expirationMinutes = 60): ?string
    {
        if (! $this->trx_in_pdf_path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($this->trx_in_pdf_path)) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute('docs.serve', now()->addMinutes($expirationMinutes), [
            'type' => 'transaction_trx_in',
            'id' => $this->id,
        ]);
    }

    public function getTrxOutPdfUrl(int $expirationMinutes = 60): ?string
    {
        if (! $this->trx_out_pdf_path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($this->trx_out_pdf_path)) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute('docs.serve', now()->addMinutes($expirationMinutes), [
            'type' => 'transaction_trx_out',
            'id' => $this->id,
        ]);
    }



    public static function boot()
    {
        parent::boot();


        static::updated(function ($transaction) {
            try {
                if ($transaction->bankAccount) {
                    $transaction->bankAccount->calculateBalance();
                }
            } catch (\Exception $e) {
                Log::error('Error in transaction updated event: ' . $e->getMessage(), [
                    'transaction_id' => $transaction->id,
                    'bank_account_id' => $transaction->bank_account_id
                ]);
            }

            try {
                if ($transaction->shouldClearProviderNeedsPaymentFlag()) {
                    $transaction->clearProviderNeedsPaymentFlag();
                }
            } catch (\Exception $e) {
                Log::error('Error clearing provider needs_payment on transaction update: ' . $e->getMessage(), [
                    'transaction_id' => $transaction->id,
                    'related_type' => $transaction->related_type,
                    'related_id' => $transaction->related_id,
                ]);
            }
        });

        static::created(function ($transaction) {
            try {
                if ($transaction->bankAccount) {
                    $transaction->bankAccount->calculateBalance();
                }
            } catch (\Exception $e) {
                Log::error('Error in transaction created event: ' . $e->getMessage(), [
                    'transaction_id' => $transaction->id,
                    'bank_account_id' => $transaction->bank_account_id
                ]);
            }

            try {
                if ($transaction->shouldClearProviderNeedsPaymentFlag()) {
                    $transaction->clearProviderNeedsPaymentFlag();
                }
            } catch (\Exception $e) {
                Log::error('Error clearing provider needs_payment on transaction create: ' . $e->getMessage(), [
                    'transaction_id' => $transaction->id,
                    'related_type' => $transaction->related_type,
                    'related_id' => $transaction->related_id,
                ]);
            }
        });


        static::deleting(function ($transaction) {
            try {
                // un pay all the invoices or bills related to this transaction
                if ($transaction->related_type === 'Invoice' && $transaction->related_id) {
                    Invoice::find($transaction->related_id)?->update(['status' => 'Unpaid']);
                }
                if ($transaction->related_type === 'Bill' && $transaction->related_id) {
                    Bill::find($transaction->related_id)?->update(['status' => 'Unpaid']);
                }
            } catch (\Exception $e) {
                Log::error('Error in transaction deleting event: ' . $e->getMessage(), [
                    'transaction_id' => $transaction->id,
                    'related_type' => $transaction->related_type,
                    'related_id' => $transaction->related_id
                ]);
            }
        });
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class)->withPivot('amount_paid');
    }

    public function bills(): BelongsToMany
    {
        return $this->belongsToMany(Bill::class)->withPivot('amount_paid');
    }

    /**
     * related_type values that point at Eloquent models (not expense category labels).
     *
     * @return array<int, string>
     */
    public static function modelRelatedTypes(): array
    {
        return ['Client', 'Provider', 'Branch', 'Patient', 'Invoice', 'Bill', 'File'];
    }

    public function hasModelRelated(): bool
    {
        return $this->related_id
            && in_array($this->related_type, self::modelRelatedTypes(), true);
    }

    public function resolveRelated(): ?Model
    {
        if (! $this->hasModelRelated()) {
            return null;
        }

        return match ($this->related_type) {
            'Client' => Client::find($this->related_id),
            'Provider' => Provider::find($this->related_id),
            'Branch' => ProviderBranch::with('provider')->find($this->related_id),
            'Patient' => Patient::find($this->related_id),
            'Invoice' => Invoice::find($this->related_id),
            'Bill' => Bill::find($this->related_id),
            'File' => File::find($this->related_id),
            default => null,
        };
    }

    public function getRelatedAttribute(): ?Model
    {
        return $this->resolveRelated();
    }

    public function getRelatedPartyLabel(): ?string
    {
        if (! $this->hasModelRelated()) {
            return null;
        }

        $related = $this->resolveRelated();

        if (! $related) {
            return null;
        }

        return match ($this->related_type) {
            'Client' => $related->company_name ?? $related->name ?? null,
            'Provider' => $related->name ?? null,
            'Branch' => $related->provider?->name ?? $related->name ?? $related->branch_name ?? null,
            'Patient' => $related->name ?? null,
            'Invoice', 'Bill', 'File' => $related->name ?? null,
            default => null,
        };
    }

    /**
     * If the transaction is related to a provider (directly or through branch),
     * mark provider as no longer needing payment.
     */
    public function clearProviderNeedsPaymentFlag(): void
    {
        $provider = null;

        if ($this->related_type === 'Provider') {
            $provider = Provider::find($this->related_id);
        } elseif ($this->related_type === 'Branch') {
            $branch = ProviderBranch::with('provider')->find($this->related_id);
            $provider = $branch?->provider;
        }

        if ($provider && $provider->needs_payment) {
            $provider->update(['needs_payment' => false]);
        }
    }

    /**
     * Only clear provider payment flag for issued outflow transactions.
     */
    public function shouldClearProviderNeedsPaymentFlag(): bool
    {
        if ($this->type !== 'Outflow') {
            return false;
        }

        // If status is not used on this record, treat it as issued.
        if (!$this->status) {
            return true;
        }

        return $this->status === 'Completed';
    }

    /**
     * Sync linked invoice paid_amount from pivot. Does not modify bank_charges — manual entry only.
     */
    public function syncLinkedInvoicePayments()
    {
        // Sync each linked invoice's paid_amount from the pivot (bank_charges are manual only).
        $this->invoices()->each(function ($invoice) {
            $paidAmount = $invoice->pivot->amount_paid ?? 0;
            $invoice->paid_amount = $paidAmount;
            $invoice->save();

            $invoice->checkStatus();
        });
    }

    /** @deprecated Use syncLinkedInvoicePayments() */
    public function calculateBankCharges(): void
    {
        $this->syncLinkedInvoicePayments();
    }

    public function attachInvoices(array $invoiceIds)
    {
        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice) {
                // Check if the invoice is already attached
                if (!$this->invoices()->where('invoice_id', $invoice->id)->exists()) {
                    // Attach with the full invoice amount as paid_amount
                    $this->invoices()->attach($invoice->id, [
                        'amount_paid' => $invoice->total_amount
                    ]);

                    // Update invoice paid_amount
                    $invoice->paid_amount = $invoice->total_amount;
                    $invoice->save();

                    // Update invoice status
                    $invoice->checkStatus();
                }
            }
        }

        // Sync linked invoice payments after attaching invoices
        $this->syncLinkedInvoicePayments();
        app(\App\Services\TransactionDocumentationService::class)->syncAndRecalculate($this);
    }

    public function updateInvoicePaidAmount(Invoice $invoice, float $amount)
    {
        // Update the pivot table
        $this->invoices()->updateExistingPivot($invoice->id, [
            'amount_paid' => $amount
        ]);

        // Update the invoice's paid_amount
        $invoice->paid_amount = $amount;
        $invoice->save();

        // Update invoice status
        $invoice->checkStatus();

        // Sync linked invoice payments
        $this->syncLinkedInvoicePayments();
    }

    public function attachBills(array $billIds)
    {
        foreach ($billIds as $billId) {
            $bill = Bill::find($billId);
            if ($bill) {
                // Check if the bill is already attached
                if (!$this->bills()->where('bill_id', $bill->id)->exists()) {
                    // Attach with the full bill amount as paid_amount
                    $this->bills()->attach($bill->id, [
                        'amount_paid' => $bill->total_amount
                    ]);

                    // Temporarily disable model events to prevent circular dependency
                    $bill->withoutEvents(function () use ($bill) {
                        $bill->paid_amount = $bill->total_amount;
                        $bill->status = 'Paid';
                        $bill->payment_date = now();
                        $bill->save();
                    });
                }
            }
        }

        // Recalculate documentation after attaching bills
        app(\App\Services\TransactionDocumentationService::class)->syncAndRecalculate($this);
    }

    /**
     * Attach bills to transaction without marking them as paid (for draft transactions)
     */
    public function attachBillsForDraft(array $billIds)
    {
        foreach ($billIds as $billId) {
            $bill = Bill::find($billId);
            if ($bill) {
                // Check if the bill is already attached
                if (!$this->bills()->where('bill_id', $bill->id)->exists()) {
                    // Attach with the remaining amount as paid_amount (not the full amount)
                    $remainingAmount = $bill->total_amount - $bill->paid_amount;
                    $this->bills()->attach($bill->id, [
                        'amount_paid' => $remainingAmount
                    ]);
                    
                    // Don't update the bill status - keep it as is
                }
            }
        }

        app(\App\Services\TransactionDocumentationService::class)->syncAndRecalculate($this);
    }

    /**
     * Check if the attachment is a Google Drive link
     */
    public function isGoogleDriveAttachment(): bool
    {
        return $this->attachment_path && str_contains($this->attachment_path, 'drive.google.com');
    }

    /**
     * Check if the attachment is an external link (Google Drive, HTTP, etc.).
     */
    public function isExternalAttachment(): bool
    {
        if (! $this->attachment_path) {
            return false;
        }

        if ($this->isGoogleDriveAttachment() || $this->isUrl()) {
            return true;
        }

        return str_contains($this->attachment_path, '://')
            || str_starts_with($this->attachment_path, 'www.');
    }

    /**
     * Check if the attachment is a local storage file (not a URL).
     */
    public function isLocalStorageFile(): bool
    {
        if (! $this->attachment_path || $this->isExternalAttachment()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the attachment is an uploaded file
     */
    public function isUploadedFile(): bool
    {
        return $this->isLocalStorageFile();
    }

    /**
     * Check if the attachment is a URL
     */
    public function isUrl(): bool
    {
        return $this->attachment_path && str_starts_with($this->attachment_path, 'http');
    }

    /**
     * Get the attachment display text
     */
    public function getAttachmentDisplayText(): string
    {
        if (!$this->attachment_path) {
            return 'No Document';
        }
        
        if ($this->isGoogleDriveAttachment()) {
            return 'View Google Drive Document';
        }
        
        if (str_starts_with($this->attachment_path, 'http')) {
            return 'View Document Link';
        }
        
        if ($this->isLocalStorageFile()) {
            return 'View Document: ' . basename($this->attachment_path);
        }
        
        return 'View Document';
    }

    /**
     * Resolve a browser-ready URL for the attachment.
     */
    public function getAttachmentUrl(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        if ($this->isExternalAttachment()) {
            $url = $this->attachment_path;

            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                $url = 'https://' . ltrim($url, '/');
            }

            return $url;
        }

        if ($this->isLocalStorageFile()
            && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->attachment_path)) {
            return asset('storage/' . $this->attachment_path);
        }

        return null;
    }

    /**
     * Get the Google Drive file ID from the URL
     */
    public function getGoogleDriveFileId(): ?string
    {
        if (!$this->isGoogleDriveAttachment()) {
            return null;
        }

        // Extract file ID from Google Drive URL
        if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $this->attachment_path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Finalize the transaction and mark attached bills as paid
     */
    public function finalizeTransaction()
    {
        if ($this->status !== 'Draft') {
            throw new \Exception('Only draft transactions can be finalized');
        }

        // Mark all attached bills as paid
        $this->bills()->each(function ($bill) {
            $amountPaid = $bill->pivot->amount_paid ?? 0;
            
            // Update the bill's paid amount
            $bill->paid_amount += $amountPaid;
            $bill->payment_date = $this->date;
            
            // Check if the bill is now fully paid
            if ($bill->paid_amount >= $bill->total_amount) {
                $bill->status = 'Paid';
            } else {
                $bill->status = 'Partial';
            }
            
            $bill->save();
        });

        // Update transaction status
        $this->status = 'Completed';
        $this->save();

        return $this;
    }

    /**
     * Check if the transaction has a local document on disk.
     */
    public function hasLocalDocument(): bool
    {
        return $this->isLocalStorageFile()
            && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->attachment_path);
    }

    /**
     * Generate a signed URL for the transaction document
     * 
     * @param int $expirationMinutes Expiration time in minutes (default: 60)
     * @return string|null
     */
    public function getDocumentSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (! $this->hasLocalDocument()) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute('docs.serve', now()->addMinutes($expirationMinutes), [
            'type' => 'transaction',
            'id' => $this->id
        ]);
    }

    /**
     * Generate a signed URL for document metadata
     * 
     * @param int $expirationMinutes Expiration time in minutes (default: 60)
     * @return string|null
     */
    public function getDocumentMetadataSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (! $this->hasLocalDocument()) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute('docs.metadata', now()->addMinutes($expirationMinutes), [
            'type' => 'transaction',
            'id' => $this->id
        ]);
    }
}
