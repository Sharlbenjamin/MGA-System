<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
        ];
    }

    public function setStartTimeAttribute($value): void
    {
        $this->attributes['start_time'] = $value instanceof \Carbon\Carbon
            ? $value->format('H:i:s')
            : \Carbon\Carbon::parse($value)->format('H:i:s');
    }

    public function setEndTimeAttribute($value): void
    {
        $this->attributes['end_time'] = $value instanceof \Carbon\Carbon
            ? $value->format('H:i:s')
            : \Carbon\Carbon::parse($value)->format('H:i:s');
    }

    public function shiftSchedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    public function getTimeRangeAttribute(): string
    {
        $start = is_string($this->start_time) ? \Carbon\Carbon::parse($this->start_time) : $this->start_time;
        $end = is_string($this->end_time) ? \Carbon\Carbon::parse($this->end_time) : $this->end_time;

        return $start->format('H:i') . ' - ' . $end->format('H:i');
    }
}
