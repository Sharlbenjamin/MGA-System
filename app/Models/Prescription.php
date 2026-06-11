<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\LogsActivity;

class Prescription extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'file_id',
        'name',
        'serial',
        'date',
        'document_path',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'file_id' => 'integer',
    ];

    public function getActivityReference(): ?string
    {
        $ref = $this->file?->mga_reference ?? 'File #' . $this->file_id;
        return "Rx {$this->serial} ({$ref})";
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function drugs(): HasMany
    {
        return $this->hasMany(Drug::class);
    }

    /**
     * Check if the prescription has a local document
     */
    public function hasLocalDocument(): bool
    {
        return !empty($this->document_path);
    }

    /**
     * Generate a signed URL for the prescription document
     * 
     * @param int $expirationMinutes Expiration time in minutes (default: 60)
     * @return string|null
     */
    public function getDocumentSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (!$this->hasLocalDocument()) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute('docs.serve', now()->addMinutes($expirationMinutes), [
            'type' => 'prescription',
            'id' => $this->id
        ]);
    }

    /**
     * Generate a signed URL for document metadata
     * 
     * @param int $expirationMinutes Expiration time in minutes (default: 60)
     * @return string|null
     */
    public function getDocumentMetadataSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (!$this->hasLocalDocument()) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute('docs.metadata', now()->addMinutes($expirationMinutes), [
            'type' => 'prescription',
            'id' => $this->id
        ]);
    }
}
