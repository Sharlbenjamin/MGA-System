<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'transaction_group_id',
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
            if ($invoice->isDirty('status') && $invoice->status === 'sent') {
                $invoice->invoice_date = now();
                $invoice->due_date = now()->addDays(60);
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
        return ['draft', 'sent', 'overdue', 'paid'];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactionGroup(): BelongsTo
    {
        return $this->belongsTo(TransactionGroup::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'related');
    }

    public function calculateTotal()
    {
        $items = $this->items;

        $subtotal = $items->sum(function ($item) {
            return $item->amount - $item->discount;
        });

        $this->total_amount = $subtotal;
        $this->tax = $subtotal * 0.21; // 21% VAT

        if ($this->discount > 0) {
            $this->total_amount = $this->total_amount - $this->discount;
        }

        $this->save();
    }

    public function getRemainingAmountAttribute(): float
    {
        return $this->total_amount - ($this->paid_amount ?? 0);
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'paid') {
            return false;
        }
        return $this->due_date->isPast();
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'paid';
    }

    public function markAsPaid()
    {
        $this->status = 'paid';
        $this->payment_date = now();
        $this->save();
    }

    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(function ($item) {
            return $item->amount - $item->discount;
        });
    }

    public function getDiscountedSubtotalAttribute(): float
    {
        return $this->subtotal - ($this->discount ?? 0);
    }

    public function getTaxAmountAttribute(): float
    {
        return $this->discounted_subtotal * self::TAX_RATE;
    }

    public function getFinalTotalAttribute(): float
    {
        return $this->total_amount;
    }
}
