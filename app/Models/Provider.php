<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Provider extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_id',
        'status',
        'type',
        'name',
        'payment_due',
        'payment_method',
        'comment',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function branches()
    {
        return $this->hasMany(ProviderBranch::class);
    }
    public function leads()
    {
        return $this->hasMany(ProviderLead::class, 'provider_id');
    }
    public function latestLead()
    {
        return $this->hasOne(ProviderLead::class)->latestOfMany('last_contact_date');
    }

    public function requests(): HasManyThrough
    {
        return $this->hasManyThrough(
            File::class,  // Final model
            ProviderBranch::class,  // Intermediate model
            'provider_id',     // Foreign key on ProviderBranch table
            'provider_branch_id',    // Foreign key on requests table
            'id',            // Local key on provider table
            'id'             // Local key on request table
        );
    }
}
