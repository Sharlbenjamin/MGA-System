<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchCity extends Model
{
    protected $fillable = [
        'provider_branch_id',
        'city_id'
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(ProviderBranch::class, 'provider_branch_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }
}