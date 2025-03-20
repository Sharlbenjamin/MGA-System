<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'provider_lead_id',
        'user_id',
        'method',
        'status',
        'content',
        'positive',
        'interaction_date',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function providerLead()
    {
        return $this->belongsTo(ProviderLead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}