<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FileFee extends Model
{
    public const TIER_SIMPLE = 'simple';

    public const TIER_MIDDLE = 'middle';

    public const TIER_COMPLEX = 'complex';

    public const TIERS = [
        self::TIER_SIMPLE,
        self::TIER_MIDDLE,
        self::TIER_COMPLEX,
    ];

    protected $fillable = [
        'tier',
        'service_type_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'file_fee_country')
            ->withTimestamps();
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'file_fee_client')
            ->withTimestamps();
    }

    public function isTierFee(): bool
    {
        return filled($this->tier);
    }

    public function isServiceTypeFee(): bool
    {
        return filled($this->service_type_id) && ! $this->isTierFee();
    }

    public function appliesToAllCountries(): bool
    {
        if ($this->relationLoaded('countries')) {
            return $this->countries->isEmpty();
        }

        return ! $this->countries()->exists();
    }

    public function appliesToAllClients(): bool
    {
        if ($this->relationLoaded('clients')) {
            return $this->clients->isEmpty();
        }

        return ! $this->clients()->exists();
    }

    public function tierLabel(): ?string
    {
        return $this->tier ? ucfirst($this->tier) : null;
    }
}
