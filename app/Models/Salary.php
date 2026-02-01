<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Salary extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'year',
        'month',
        'base_salary',
        'adjustments',
        'deductions',
        'net_salary',
        'is_locked',
        'locked_at',
        'locked_by_id',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'adjustments' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_id');
    }

    public function getMonthNameAttribute(): string
    {
        return \Carbon\Carbon::createFromDate($this->year, $this->month, 1)->format('F Y');
    }
}
