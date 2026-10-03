<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    public const STATUS_INTERVIEWING = 'interviewing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FORMER = 'former';

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_INTERVIEWING => 'Interviewing',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_FORMER => 'Former',
        ];
    }

    protected $fillable = [
        'user_id',
        'job_title_id',
        'manager_id',
        'bank_account_id',
        'name',
        'date_of_birth',
        'gender',
        'national_id',
        'phone',
        'basic_salary',
        'full_salary',
        'social_insurance_salary',
        'expected_salary',
        'offered_salary',
        'start_date',
        'end_date',
        'interview_date',
        'signed_contract_path',
        'signed_contract',
        'social_insurance_number',
        'photo_id_path',
        'cv_path',
        'linkedin_url',
        'employment_type',
        'notice_period',
        'english_level',
        'flexible_shifts',
        'reason_for_leaving',
        'has_laptop',
        'department',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'interview_date' => 'datetime',
            'signed_contract' => 'boolean',
            'flexible_shifts' => 'boolean',
            'has_laptop' => 'boolean',
            'basic_salary' => 'decimal:2',
            'full_salary' => 'decimal:2',
            'social_insurance_salary' => 'decimal:2',
            'expected_salary' => 'decimal:2',
            'offered_salary' => 'decimal:2',
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

    public function shiftSchedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(Salary::class);
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
