<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAttachment extends Model
{
    protected $fillable = [
        'transaction_id',
        'type',
        'file_path',
        'original_name',
        'uploaded_by',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function hasLocalDocument(): bool
    {
        if (! $this->file_path) {
            return false;
        }

        return ! app(\App\Services\DocumentLinkResolver::class)->isExternalPath($this->file_path)
            && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->file_path);
    }

    public function getDocumentSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (! $this->hasLocalDocument()) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute('docs.serve', now()->addMinutes($expirationMinutes), [
            'type' => 'transaction_attachment',
            'id' => $this->id,
        ]);
    }
}
