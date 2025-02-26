<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderLead extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'city_id',
        'service_types',
        'type',
        'provider_id',
        'name',
        'email',
        'phone',
        'communication_method',
        'status',
        'last_contact_date',
        'comment',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'provider_id' => 'integer',
        'last_contact_date' => 'date',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function serviceTypes()
    {
        return $this->belongsToMany(ServiceType::class, 'provider_lead_service_type', 'provider_lead_id', 'service_type_id')
            ->withTimestamps();
    }
}
