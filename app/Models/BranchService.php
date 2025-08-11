<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchService extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_branch_id',
        'service_type_id',
        'day_cost',
        'night_cost',
        'weekend_cost',
        'weekend_night_cost',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'provider_branch_id' => 'integer',
        'service_type_id' => 'integer',
        'day_cost' => 'decimal:2',
        'night_cost' => 'decimal:2',
        'weekend_cost' => 'decimal:2',
        'weekend_night_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function providerBranch(): BelongsTo
    {
        return $this->belongsTo(ProviderBranch::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }
}
