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
        'name',
        'tier',
        'service_type_id',
        'amount',
        'simple_amount',
        'middle_amount',
        'complex_amount',
        'simple_max_total',
        'middle_max_total',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'simple_amount' => 'decimal:2',
        'middle_amount' => 'decimal:2',
        'complex_amount' => 'decimal:2',
        'simple_max_total' => 'decimal:2',
        'middle_max_total' => 'decimal:2',
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

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'file_fee_city')
            ->withTimestamps();
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'file_fee_client')
            ->withTimestamps();
    }

    public function isTierPackage(): bool
    {
        return $this->service_type_id === null
            && ($this->simple_amount !== null
                || $this->middle_amount !== null
                || $this->complex_amount !== null);
    }

    /** @deprecated Legacy single-tier rows before package migration */
    public function isTierFee(): bool
    {
        return filled($this->tier);
    }

    public function isServiceTypeFee(): bool
    {
        return filled($this->service_type_id) && ! $this->isTierPackage();
    }

    public function appliesToAllCountries(): bool
    {
        if ($this->relationLoaded('countries')) {
            return $this->countries->isEmpty();
        }

        return ! $this->countries()->exists();
    }

    public function appliesToAllCities(): bool
    {
        if ($this->relationLoaded('cities')) {
            return $this->cities->isEmpty();
        }

        return ! $this->cities()->exists();
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

    public function amountForTier(string $tier): ?float
    {
        return match ($tier) {
            self::TIER_SIMPLE => $this->simple_amount !== null ? (float) $this->simple_amount : null,
            self::TIER_MIDDLE => $this->middle_amount !== null ? (float) $this->middle_amount : null,
            self::TIER_COMPLEX => $this->complex_amount !== null ? (float) $this->complex_amount : null,
            default => null,
        };
    }

    public function tierCaps(): array
    {
        return [
            'simple_max' => $this->simple_max_total !== null
                ? (float) $this->simple_max_total
                : (float) config('invoice.file_fee_tiers.simple.max_total', 350),
            'middle_max' => $this->middle_max_total !== null
                ? (float) $this->middle_max_total
                : (float) config('invoice.file_fee_tiers.middle.max_total', 1000),
        ];
    }
}
