<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Client extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_name',
        'type',
        'status',
        'initials',
        'number_requests',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function files(): HasManyThrough
    {
        return $this->hasManyThrough(
            File::class,  // Final model
            Patient::class,  // Intermediate model
            'client_id',     // Foreign key on patients table
            'patient_id',    // Foreign key on requests table
            'id',            // Local key on clients table
            'id'             // Local key on patients table
        );
    }
}
