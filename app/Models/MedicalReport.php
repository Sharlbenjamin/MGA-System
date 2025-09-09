<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalReport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'status',
        'file_id',
        'complain',
        'diagnosis',
        'history',
        'temperature',
        'blood_pressure',
        'pulse',
        'examination',
        'advice',
        'document_path',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'date' => 'date',
        'file_id' => 'integer',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Check if the medical report has a local document
     */
    public function hasLocalDocument(): bool
    {
        return !empty($this->document_path);
    }

    /**
     * Generate a signed URL for the medical report document
     * 
     * @param int $expirationMinutes Expiration time in minutes (default: 60)
     * @return string|null
     */
    public function getDocumentSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (!$this->hasLocalDocument()) {
            return null;
        }

        return route('docs.serve', [
            'type' => 'medical_report',
            'id' => $this->id
        ], true, $expirationMinutes);
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

        return route('docs.metadata', [
            'type' => 'medical_report',
            'id' => $this->id
        ], true, $expirationMinutes);
    }
}
