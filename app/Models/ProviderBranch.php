<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderBranch extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'provider_id',
        'branch_name',
        'city_id',
        'status',
        'priority',
        'service_type_id',
        'communication_method',
        'day_cost',
        'night_cost',
        'weekend_cost',
        'weekend_night_cost',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'provider_id' => 'integer',
        'service_type_id' => 'integer',
        'day_cost' => 'decimal:2',
        'night_cost' => 'decimal:2',
        'weekend_cost' => 'decimal:2',
        'weekend_night_cost' => 'decimal:2',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }
}
