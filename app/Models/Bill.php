<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'transaction_group_id',
        'paid_amount',
        'uploaded_pdf_path',
    ];

    protected $casts = [
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'payment_date' => 'date',
        'paid_amount' => 'decimal:2',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
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
        return $this->hasMany(BillItem::class);
    }
}
