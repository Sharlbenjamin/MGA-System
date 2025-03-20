<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'department', 'contact_id', 'file_id', 'taskable_type', 'taskable_id',
        'title', 'description', 'due_date', 'is_done', 'done_by'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function getContactAttribute()
    {
        if (!$this->taskable) {
            return null;
        }

        // If taskable is Lead or ProviderLead, return the contact method field
        if ($this->taskable instanceof \App\Models\Lead) {
            $contactMethodField = $this->contact_method;
            return $this->taskable->$contactMethodField ?? null;
        }

        if ($this->taskable instanceof \App\Models\ProviderLead) {
            $contactMethodField = $this->communication_method;
            return $this->taskable->$contactMethodField ?? null;
        }

        // If taskable is Patient, return null (do not search for contacts)
        if ($this->taskable instanceof \App\Models\Patient) {
            return null;
        }

        // If taskable is Client or Provider, get the contact
        if ($this->taskable instanceof \App\Models\Client || $this->taskable instanceof \App\Models\Provider) {
            $query = $this->taskable->contacts();

            // If department is "Operation", filter by contact title "Operation"
            if ($this->department === 'Operation') {
                $query->where('title', 'Operation');
            }

            return $query->first();
        }

        return null;
    }
}