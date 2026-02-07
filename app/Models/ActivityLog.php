<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_reference',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Human-readable model name for display (e.g. "Case", "Provider", "Client").
     */
    public function getSubjectTypeLabelAttribute(): string
    {
        $type = $this->subject_type;
        if (!$type) {
            return 'Unknown';
        }
        $short = class_basename($type);
        return match ($short) {
            'File' => 'Case',
            'Provider' => 'Provider',
            'Client' => 'Client',
            'Patient' => 'Patient',
            'Gop' => 'GOP',
            'Bill' => 'Bill',
            'Comment' => 'Comment',
            'MedicalReport' => 'Medical Report',
            'Prescription' => 'Prescription',
            'Appointment' => 'Appointment',
            'Task' => 'Task',
            'BankAccount' => 'Bank Account',
            'Invoice' => 'Invoice',
            'FileAssignment' => 'Assignment',
            'ProviderBranch' => 'Branch',
            'ProviderLead' => 'Provider Lead',
            'Lead' => 'Lead',
            default => $short,
        };
    }

    /**
     * Scope: activity for this record plus all its related (relation manager) records.
     * Used to show "all activity" on the File/Provider/Client/Patient view.
     */
    public function scopeForRecord(Builder $query, Model $record): Builder
    {
        $recordClass = get_class($record);
        $recordId = $record->getKey();

        $query->where(function (Builder $q) use ($recordClass, $recordId, $record) {
            $q->where(function (Builder $q2) use ($recordClass, $recordId) {
                $q2->where('subject_type', $recordClass)->where('subject_id', $recordId);
            });

            if ($record instanceof File) {
                foreach (['gops', 'bills', 'comments', 'medicalReports', 'prescriptions', 'appointments', 'tasks', 'bankAccounts', 'invoices', 'fileAssignments'] as $relation) {
                    $ids = $record->$relation()->pluck('id');
                    if ($ids->isNotEmpty()) {
                        $relationClass = $record->$relation()->getRelated()::class;
                        $q->orWhere(function (Builder $q2) use ($relationClass, $ids) {
                            $q2->where('subject_type', $relationClass)->whereIn('subject_id', $ids);
                        });
                    }
                }
            }

            if ($record instanceof Provider) {
                foreach (['branches', 'leads', 'bankAccounts', 'bills'] as $relation) {
                    $ids = $record->$relation()->pluck('id');
                    if ($ids->isNotEmpty()) {
                        $relationClass = $record->$relation()->getRelated()::class;
                        $q->orWhere(function (Builder $q2) use ($relationClass, $ids) {
                            $q2->where('subject_type', $relationClass)->whereIn('subject_id', $ids);
                        });
                    }
                }
            }

            if ($record instanceof Client) {
                foreach (['leads', 'bankAccounts'] as $relation) {
                    $ids = $record->$relation()->pluck('id');
                    if ($ids->isNotEmpty()) {
                        $relationClass = $record->$relation()->getRelated()::class;
                        $q->orWhere(function (Builder $q2) use ($relationClass, $ids) {
                            $q2->where('subject_type', $relationClass)->whereIn('subject_id', $ids);
                        });
                    }
                }
                $invoiceIds = Invoice::whereIn('patient_id', $record->patients()->pluck('id'))->pluck('id');
                if ($invoiceIds->isNotEmpty()) {
                    $q->orWhere(function (Builder $q2) use ($invoiceIds) {
                        $q2->where('subject_type', Invoice::class)->whereIn('subject_id', $invoiceIds);
                    });
                }
            }

            if ($record instanceof Patient) {
                foreach (['files', 'invoices'] as $relation) {
                    $ids = $record->$relation()->pluck('id');
                    if ($ids->isNotEmpty()) {
                        $relationClass = $record->$relation()->getRelated()::class;
                        $q->orWhere(function (Builder $q2) use ($relationClass, $ids) {
                            $q2->where('subject_type', $relationClass)->whereIn('subject_id', $ids);
                        });
                    }
                }
            }
        });

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Human-readable action description.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'Created',
            self::ACTION_UPDATED => 'Updated',
            self::ACTION_DELETED => 'Deleted',
            default => ucfirst($this->action),
        };
    }
}
