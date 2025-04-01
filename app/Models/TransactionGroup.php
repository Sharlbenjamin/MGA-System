<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionGroup extends Model
{
    protected $fillable = [
        'notes',
        'paid_at',
        'bank_charges',
        'charges_covered_by_client',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'bank_charges' => 'decimal:2',
        'charges_covered_by_client' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }
}
