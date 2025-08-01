<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PriceList extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'city_id',
        'service_type_id',
        'provider_branch_id', // Optional: for provider-specific pricing
        'day_price',
        'weekend_price',
        'night_weekday_price',
        'night_weekend_price',
        'suggested_markup',
        'final_price_notes',
        'priceable_type', // For polymorphic relationship
        'priceable_id',   // For polymorphic relationship
    ];

    protected $casts = [
        'day_price' => 'decimal:2',
        'weekend_price' => 'decimal:2',
        'night_weekday_price' => 'decimal:2',
        'night_weekend_price' => 'decimal:2',
        'suggested_markup' => 'decimal:2',
    ];

    /**
     * Get the country that owns the price list.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the city that owns the price list.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the service type that owns the price list.
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * Get the provider branch that owns the price list (optional).
     */
    public function providerBranch(): BelongsTo
    {
        return $this->belongsTo(ProviderBranch::class);
    }

    /**
     * Polymorphic relationship for different priceable entities.
     */
    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by country.
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Scope to filter by city.
     */
    public function scopeForCity($query, $cityId)
    {
        return $query->where('city_id', $cityId);
    }

    /**
     * Scope to filter by service type.
     */
    public function scopeForServiceType($query, $serviceTypeId)
    {
        return $query->where('service_type_id', $serviceTypeId);
    }

    /**
     * Scope to filter by provider branch.
     */
    public function scopeForProviderBranch($query, $providerBranchId)
    {
        return $query->where('provider_branch_id', $providerBranchId);
    }

    /**
     * Get the display name for the price list.
     */
    public function getDisplayNameAttribute(): string
    {
        $parts = [];
        
        if ($this->country) {
            $parts[] = $this->country->name;
        }
        
        if ($this->city) {
            $parts[] = $this->city->name;
        }
        
        if ($this->serviceType) {
            $parts[] = $this->serviceType->name;
        }
        
        if ($this->providerBranch) {
            $parts[] = $this->providerBranch->branch_name;
        }
        
        return implode(' - ', $parts);
    }

    /**
     * Calculate suggested prices based on provider branch costs.
     */
    public function calculateSuggestedPrices(float $markup = 1.25): array
    {
        $suggested = [];
        
        if ($this->providerBranch) {
            $branch = $this->providerBranch;
            
            if ($branch->day_cost) {
                $suggested['day_price'] = round($branch->day_cost * $markup, 2);
            }
            
            if ($branch->weekend_cost) {
                $suggested['weekend_price'] = round($branch->weekend_cost * $markup, 2);
            }
            
            if ($branch->night_cost) {
                $suggested['night_weekday_price'] = round($branch->night_cost * $markup, 2);
            }
            
            if ($branch->weekend_night_cost) {
                $suggested['night_weekend_price'] = round($branch->weekend_night_cost * $markup, 2);
            }
        }
        
        return $suggested;
    }

    /**
     * Get average provider branch costs for the selected criteria.
     */
    public function getAverageProviderCosts(): array
    {
        $query = ProviderBranch::query()
            ->where('status', 'Active');
        
        if ($this->country_id) {
            $query->whereHas('provider', function ($q) {
                $q->where('country_id', $this->country_id);
            });
        }
        
        if ($this->city_id) {
            $query->where('city_id', $this->city_id);
        }
        
        if ($this->service_type_id) {
            $serviceTypeName = $this->serviceType->name;
            $query->whereJsonContains('service_types', $serviceTypeName);
        }
        
        $branches = $query->get();
        
        if ($branches->isEmpty()) {
            return [
                'day_cost' => 0,
                'weekend_cost' => 0,
                'night_cost' => 0,
                'weekend_night_cost' => 0,
                'count' => 0,
            ];
        }
        
        return [
            'day_cost' => round($branches->avg('day_cost'), 2),
            'weekend_cost' => round($branches->avg('weekend_cost'), 2),
            'night_cost' => round($branches->avg('night_cost'), 2),
            'weekend_night_cost' => round($branches->avg('weekend_night_cost'), 2),
            'count' => $branches->count(),
        ];
    }
} 