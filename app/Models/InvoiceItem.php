<?php

namespace App\Models;

use App\Services\InvoiceFileFeeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    public const TYPE_BILL = 'bill';

    public const TYPE_FILE_FEE = 'file_fee';

    protected $fillable = [
        'invoice_id',
        'item_type',
        'description',
        'amount',
        'discount',
        'tax',
    ];

    protected $attributes = [
        'item_type' => self::TYPE_BILL,
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

    public function isFileFeeItem(): bool
    {
        return $this->item_type === self::TYPE_FILE_FEE;
    }

    public function isBillRelatedItem(): bool
    {
        return ! $this->isFileFeeItem();
    }

    protected static function booted()
    {
        static::created(function (InvoiceItem $invoiceItem) {
            static::syncFileFeeAndTotal($invoiceItem);
        });

        static::updated(function (InvoiceItem $invoiceItem) {
            static::syncFileFeeAndTotal($invoiceItem);
        });

        static::deleted(function (InvoiceItem $invoiceItem) {
            static::syncFileFeeAndTotal($invoiceItem);
        });
    }

    protected static function syncFileFeeAndTotal(InvoiceItem $invoiceItem): void
    {
        $invoice = $invoiceItem->invoice;

        if (! $invoiceItem->isFileFeeItem()) {
            app(InvoiceFileFeeService::class)->syncForInvoice($invoice);
        }

        $invoice->refresh();
        $invoice->calculateTotal();
    }
}
