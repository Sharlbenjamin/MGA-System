<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    protected $fillable = [
        'bill_id',
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

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
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
        static::created(function ($billItem) {
            $billItem->bill->calculateTotal();
        });

        static::updated(function ($billItem) {
            $billItem->bill->calculateTotal();
        });

        static::deleted(function ($billItem) {
            $billItem->bill->calculateTotal();
        });
    }
}
