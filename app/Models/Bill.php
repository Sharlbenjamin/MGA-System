<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Bill extends Model
{
    protected $fillable = [
        'name',
        'file_id',
        'provider_id',
        'branch_id',
        'bank_account_id',
        'due_date',
        'total_amount',
        'discount',
        'status',
        'payment_date',
        'transaction_id',
        'paid_amount',
        'bill_google_link',
        'bill_date',
        'bill_document_path',
    ];

    protected $casts = [
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'payment_date' => 'date',
        'paid_amount' => 'decimal:2',
        'bill_date' => 'date',
    ];

    // Mutators
    public function setBankAccountIdAttribute($value)
    {
        $this->attributes['bank_account_id'] = ($value == 0 || $value == '0') ? null : $value;
    }

    // relations      relations      relations       relations        relations        relations

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(ProviderBranch::class, 'branch_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    // Removed incorrect patient relationship - bills are related to patients through files

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'bill_transaction')->withPivot('amount_paid');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    // Boot      Boot      Boot      Boot      Boot      Boot      Boot      Boot      Boot

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bill) {
            if (!$bill->name) {
                // Generate bill number with sequence
                $bill->name = static::generateBillNumber($bill);
            }
                $bill->bill_date = now();
                $bill->due_date = now()->addDays(60);
                $bill->total_amount = 0;
                $bill->discount = 0;
                $bill->paid_amount = 0;
                
                // Auto-populate provider_id and branch_id from file
                if ($bill->file && $bill->file->providerBranch && $bill->file->providerBranch->provider) {
                    $bill->provider_id = $bill->file->providerBranch->provider_id;
                    $bill->branch_id = $bill->file->provider_branch_id;
                }
        });

        static::updating(function ($bill) {
            // If status is being changed to sent
            if ($bill->isDirty('status') && $bill->status === 'Sent') {
                $bill->bill_date = now();
                $bill->due_date = now()->addDays(60);
            }

            // If file_id is being changed, regenerate the bill name
            if ($bill->isDirty('file_id')) {
                $bill->name = static::generateBillNumber($bill);
            }
            
            // Auto-populate provider_id and branch_id from file if they're missing
            if ((!$bill->provider_id || !$bill->branch_id) && $bill->file) {
                if (!$bill->provider_id && $bill->file->providerBranch && $bill->file->providerBranch->provider) {
                    $bill->provider_id = $bill->file->providerBranch->provider_id;
                }
                if (!$bill->branch_id && $bill->file->provider_branch_id) {
                    $bill->branch_id = $bill->file->provider_branch_id;
                }
            }
        });

        static::updated(function ($bill) {
            if ($bill->isDirty('paid_amount')) {
                $bill->checkStatus();
                // Get the first transaction if it exists
                $transaction = $bill->transactions()->first();
                if ($transaction && method_exists($transaction, 'calculateBankCharges')) {
                    $transaction->calculateBankCharges();
                }
            }
        });
    }

    protected static function generateBillNumber($bill)
    {
        // Get the file reference
        $fileReference = $bill->file ? $bill->file->mga_reference : 'UNKNOWN';
        
        // Get the latest bill number for this file and increment
        $latestBill = static::where('name', 'like', $fileReference . '-Bill-%')
            ->orderByRaw('CAST(SUBSTRING(name, -2) AS UNSIGNED) DESC')
            ->first();

        $number = $latestBill
            ? (int)substr($latestBill->name, -2) + 1
            : 1;

        return $fileReference . '-Bill-' . str_pad($number, 2, '0', STR_PAD_LEFT);
    }

    // Calculations      Calculations      Calculations       Calculations        Calculations


    public function calculateTotal()
    {
        $subtotal = $this->subtotal;
        $this->calculateDiscount();
        $this->total_amount = $subtotal - $this->discount;
        $this->save();
    }

    public function getRemaining_AmountAttribute(): float
    {
        return $this->total_amount - ($this->paid_amount ?? 0);
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'Paid';
    }

    public function calculateDiscount()
    {
        $discount = $this->items->sum(function ($item) {
            return $item->discount;
        });
        $this->discount = $discount;
    }

    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(function ($item) {
            return $item->amount;
        });
    }

    public function checkStatus()
    {
        if($this->paid_amount < $this->total_amount && $this->paid_amount > 0) {
            $this->markAsPartial();
        }elseif ($this->paid_amount == $this->total_amount) {
            $this->markAsPaid();
        }elseif ($this->paid_amount == 0) {
            $this->markAsUnpaid();
        }
    }

    public function markAsPaid()
    {
        $transaction = $this->transactions()->first();
        $now = $transaction?->date ?? now();
        $this->status = 'Paid';
        $this->payment_date = $now;
        $this->save();
    }

    public function markAsPartial()
    {
        $transaction = $this->transactions()->first();
        $now = $transaction?->date ?? now();
        $this->status = 'Partial';
        $this->payment_date = $now;
        $this->save();
    }

    public function markAsUnpaid()
    {
        $this->status = 'Unpaid';
        $this->payment_date = null;
        $this->save();
    }


    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'Paid') {
            return false;
        }
        return $this->due_date->isPast();
    }

    /**
     * Ensure provider and branch relationships are populated from file if missing
     */
    public function ensureProviderAndBranchRelationships()
    {
        if (!$this->provider_id && $this->file && $this->file->providerBranch && $this->file->providerBranch->provider) {
            $this->provider_id = $this->file->providerBranch->provider_id;
        }
        
        if (!$this->branch_id && $this->file && $this->file->provider_branch_id) {
            $this->branch_id = $this->file->provider_branch_id;
        }
        
        if ($this->isDirty(['provider_id', 'branch_id'])) {
            $this->save();
        }
    }

    /**
     * Get the provider name with fallback
     */
    public function getProviderNameAttribute(): string
    {
        return $this->provider?->name ?? 'No Provider';
    }

    /**
     * Get the branch name with fallback
     */
    public function getBranchNameAttribute(): string
    {
        return $this->branch?->branch_name ?? 'No Branch';
    }

    /**
     * Get the provider's bank account IBAN
     */
    public function getProviderBankIbanAttribute(): string
    {
        return $this->provider?->bankAccounts?->first()?->iban ?? 'No IBAN';
    }

    /**
     * Get the provider's bank account details
     */
    public function getProviderBankAccountAttribute()
    {
        return $this->provider?->bankAccounts?->first();
    }

    /**
     * Get the BK status for this bill's file
     */
    public function getBkStatusAttribute(): string
    {
        $firstInvoice = $this->file?->invoices?->first();
        if (!$firstInvoice) {
            return 'BK Not Received';
        }
        return $firstInvoice->status === 'Paid' ? 'BK Received' : 'BK Not Received';
    }

    /**
     * Check if the bill has a local document
     */
    public function hasLocalDocument(): bool
    {
        return !empty($this->bill_document_path);
    }

    /**
     * Generate a signed URL for the bill document
     * 
     * @param int $expirationMinutes Expiration time in minutes (default: 60)
     * @return string|null
     */
    public function getDocumentSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (!$this->hasLocalDocument()) {
            return null;
        }

        return route('docs.serve', [
            'type' => 'bill',
            'id' => $this->id
        ], true, $expirationMinutes);
    }

    /**
     * Generate a signed URL for document metadata
     * 
     * @param int $expirationMinutes Expiration time in minutes (default: 60)
     * @return string|null
     */
    public function getDocumentMetadataSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (!$this->hasLocalDocument()) {
            return null;
        }

        return route('docs.metadata', [
            'type' => 'bill',
            'id' => $this->id
        ], true, $expirationMinutes);
    }
}
