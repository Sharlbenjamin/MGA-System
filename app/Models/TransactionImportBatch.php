<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionImportBatch extends Model
{
    protected $fillable = [
        'filename',
        'imported_by',
        'total_rows',
        'imported_count',
        'skipped_duplicates',
        'status',
        'notes',
    ];

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'import_batch_id');
    }
}
