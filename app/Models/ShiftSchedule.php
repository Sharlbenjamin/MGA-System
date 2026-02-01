<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'scheduled_date',
        'location_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public static function locationTypes(): array
    {
        return [
            'on_site' => 'On site',
            'remote' => 'Remote',
            'hybrid' => 'Hybrid',
            'coffeeshop' => 'Coffeeshop',
            'official_face_to_face' => 'Official face to face meeting',
        ];
    }
}
