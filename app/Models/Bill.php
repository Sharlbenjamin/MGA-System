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
    ];

    protected $casts = [
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'payment_date' => 'date',
        'paid_amount' => 'decimal:2',
        'bill_date' => 'date',
    ];

    // relations      relations      relations       relations        relations        relations

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function branch()
    {
        return $this->file->providerBranch;
    }

    public function provider()
    {
        return $this->branch->provider;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

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
            // Generate the bill number
            if (!$bill->name) {
                $bill->name = static::generateBillNumber($bill);
            }
                $bill->bill_date = now();
                $bill->due_date = now()->addDays(60);
        });

        static::updating(function ($bill) {
            // If status is being changed to sent
            if ($bill->isDirty('status') && $bill->status === 'Sent') {
                $bill->bill_date = now();
                $bill->due_date = now()->addDays(60);
            }

        });

        static::updated(function ($bill) {
            if ($bill->isDirty('paid_amount')) {
                $bill->checkStatus();
                $bill->transaction->calculateBankCharges();
            }
        });
    }

    // Calculations      Calculations      Calculations       Calculations        Calculations


    public function calculateTotal()
    {
        $this->subtotal;
        $this->calculateDiscount();
        $this->total_amount = $this->subtotal - $this->discount;
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
        $now = $this->transaction?->date ?? now();
        $this->status = 'Paid';
        $this->payment_date = $now;
        $this->save();
    }

    public function markAsPartial()
    {
        $now = $this->transaction?->date ?? now();
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
}
