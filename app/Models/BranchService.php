<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchService extends Model
{
    use HasFactory;

    protected $table = 'branch_service';

    protected $fillable = [
        'provider_branch_id',
        'service_type_id',
        'min_cost',
        'max_cost',
    ];

    protected $casts = [
        'id' => 'integer',
        'provider_branch_id' => 'integer',
        'service_type_id' => 'integer',
        'min_cost' => 'decimal:2',
        'max_cost' => 'decimal:2',
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
