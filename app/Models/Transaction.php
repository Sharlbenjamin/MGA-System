<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'charges_covered_by_client' => 'boolean',
        'bank_charges' => 'decimal:2',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }
}
