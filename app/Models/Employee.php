<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'job_title_id',
        'manager_id',
        'bank_account_id',
        'name',
        'date_of_birth',
        'national_id',
        'phone',
        'basic_salary',
        'start_date',
        'signed_contract_path',
        'signed_contract',
        'social_insurance_number',
        'photo_id_path',
        'department',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'start_date' => 'date',
            'signed_contract' => 'boolean',
            'basic_salary' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    protected static function booted(): void
    {
        static::saving(function (Employee $employee) {
            if ($employee->manager_id && $employee->manager_id === $employee->id) {
                $employee->manager_id = null;
            }
        });
    }
}
