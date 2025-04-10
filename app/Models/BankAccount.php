<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $fillable = [
        'type',
        'client_id',
        'provider_id',
        'branch_id',
        'file_id',
        'country_id',
        'address',
        'bank_name',
        'beneficiary_name',
        'beneficiary_address',
        'iban',
        'swift',
        'balance'
    ];
    protected $casts = ['balance' => 'decimal:2'];

    public function getOwnerNameAttribute(): string
    {
        if ($this->type === 'Internal') {
            return 'Med Guard';
        }

        $relations = [
            'Client' => 'client',
            'Provider' => 'provider',
            'Branch' => 'branch',
            'File' => 'file'
        ];

        $relation = $relations[$this->type] ?? null;
        return $relation ? ($this->$relation?->name ?? '') : '';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(ProviderBranch::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

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

    public function ourBank(): bool
    {
        return $this->where('type', 'Internal')->exists();
    }

    public function setTypeAttribute($value)
    {
        $this->attributes['type'] = $value;

        // Clear other IDs when type changes
        $ids = ['client_id', 'provider_id', 'branch_id', 'file_id'];
        foreach ($ids as $id) {
            if ($this->shouldClearId($value, $id)) {
                $this->attributes[$id] = null;
            }
        }
    }

    private function shouldClearId(string $type, string $idField): bool
    {
        $typeToId = [
            'Client' => 'client_id',
            'Provider' => 'provider_id',
            'Branch' => 'branch_id',
            'File' => 'file_id',
        ];

        return $idField !== ($typeToId[$type] ?? null);
    }
}
