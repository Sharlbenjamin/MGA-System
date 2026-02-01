<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'level',
        'department',
        'bonus_multiplier',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'bonus_multiplier' => 'decimal:2',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'job_title_id');
    }
}
