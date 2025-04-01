<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'amount',
        'discount',
        'tax',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getTotalAttribute(): float
    {
        return $this->amount - $this->discount + $this->tax;
    }

    public function getSubtotalAttribute(): float
    {
        return $this->amount - $this->discount;
    }

    protected static function booted()
    {
        static::created(function ($invoiceItem) {
            $invoiceItem->invoice->calculateTotal();
        });

        static::updated(function ($invoiceItem) {
            $invoiceItem->invoice->calculateTotal();
        });

        static::deleted(function ($invoiceItem) {
            $invoiceItem->invoice->calculateTotal();
        });
    }
}
