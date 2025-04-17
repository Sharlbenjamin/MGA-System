<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsToOneThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    protected $fillable = [
        'name',
        'patient_id',
        'bank_account_id',
        'due_date',
        'total_amount',
        'discount',
        'tax',
        'status',
        'payment_date',
        'transaction_id',
        'paid_amount',
        'draft_path',
        'invoice_date',
    ];

    protected $attributes = [
        'discount' => 0,
        'total_amount' => 0,
        'tax' => 0,
        'paid_amount' => 0,
    ];

    protected $casts = [
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'payment_date' => 'date',
        'paid_amount' => 'decimal:2',
        'invoice_date' => 'date',
    ];

    private const TAX_RATE = 0.21;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            // Generate the invoice number
            if (!$invoice->name) {
                $invoice->name = static::generateInvoiceNumber($invoice);
            }
                $invoice->invoice_date = now();
                $invoice->due_date = now()->addDays(60);
        });

        static::updating(function ($invoice) {
            // If status is being changed to sent
            if ($invoice->isDirty('status') && $invoice->status === 'Sent') {
                $invoice->invoice_date = now();
                $invoice->due_date = now()->addDays(60);
            }

        });

        static::updated(function ($invoice) {
            if ($invoice->isDirty('paid_amount')) {
                $invoice->checkStatus();
                $invoice->transaction->calculateBankCharges();
            }
        });
    }

    protected static function generateInvoiceNumber($invoice)
    {
        $prefix = 'MGA-INV-';

        // Get client initials through patient relationship
        $clientInitials = optional($invoice->patient->client)->initials ?? 'XX';

        // Get the latest invoice number and increment
        $latestInvoice = static::where('name', 'like', $prefix . $clientInitials . '-%')
        ->orderByRaw('CAST(SUBSTRING(name, -3) AS UNSIGNED) DESC')
        ->first();

        $number = $latestInvoice
        ? (int)substr($latestInvoice->name, -3) + 1
        : 1;

        return $prefix . $clientInitials . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    // relations      relations      relations       relations        relations        relations



    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function file(): HasOne
    {
        return $this->hasOne(File::class, 'patient_id', 'patient_id')
            ->oldest()
            ->limit(1);
    }

    public function client(): HasOneThrough
    {
        return $this->hasOneThrough(Client::class, Patient::class);
    }

    public function statuses(): array
    {
        return ['Draft', 'Sent', 'Paid', 'Unpaid', 'Partial'];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }


    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, 'invoice_transaction')
            ->withPivot('amount_paid');
    }

    //                    calculatations             calculatations            calculatations

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
