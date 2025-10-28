<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'country_id',
        'province_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'country_id' => 'integer',
        'province_id' => 'integer',
    ];

    protected $appends = [
        'city',
        'services',
        'telemedicine',
        'house_visit',
        'dental',
        'clinic',
        'cost',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class);
    }

    public function branchCities(): HasMany
    {
        return $this->hasMany(BranchCity::class, 'city_id', 'id');
    }

    public function getCityAttribute()
    {
        return $this->name;
    }

    public function getServicesAttribute()
    {
        $services = collect();
        
        foreach ($this->branchCities as $branchCity) {
            $branch = $branchCity->branch;
            if ($branch && $branch->provider && $branch->provider->status === 'Active') {
                foreach ($branch->services as $service) {
                    $services->push([
                        'service_name' => $service->name,
                        'min_cost' => $service->pivot->min_cost,
                        'max_cost' => $service->pivot->max_cost,
                        'provider_name' => $branch->provider->name,
                    ]);
                }
            }
        }
        
        return $services;
    }

    public function getTelemedicineAttribute()
    {
        return $this->formatServiceWithProviders('Telemedicine', true, 'bg-blue-50 text-blue-800 border-blue-200');
    }

    public function getHouseVisitAttribute()
    {
        return $this->formatServiceWithProviders('House Call', false, 'bg-green-50 text-green-800 border-green-200');
    }

    public function getDentalAttribute()
    {
        return $this->formatServiceWithProviders('Dental Clinic', false, 'bg-purple-50 text-purple-800 border-purple-200');
    }

    public function getClinicAttribute()
    {
        return $this->formatServiceWithProviders('Clinic Visit', false, 'bg-orange-50 text-orange-800 border-orange-200');
    }

    public function getCostAttribute()
    {
        $allProviders = collect();
        
        // Collect all providers from all services
        foreach ($this->branchCities as $branchCity) {
            $branch = $branchCity->branch;
            if ($branch && $branch->provider && $branch->provider->status === 'Active') {
                foreach ($branch->services as $service) {
                    $allProviders->push([
                        'provider_name' => $branch->provider->name,
                        'service_name' => $service->name,
                        'min_cost' => $service->pivot->min_cost,
                        'max_cost' => $service->pivot->max_cost,
                        'priority' => $branch->provider->priority ?? 999,
                    ]);
                }
            }
        }
        
        if ($allProviders->isEmpty()) {
            return "<span class='font-bold text-red-600'>No Services</span>";
        }
        
        // Group by provider and sort by priority
        $groupedProviders = $allProviders->groupBy('provider_name');
        
        // Sort providers by priority
        $sortedProviders = $groupedProviders->sortBy(function ($services) {
            return $services->first()['priority'];
        });
        
        $providerList = $sortedProviders->map(function ($services, $providerName) {
            $serviceCosts = $services->map(function ($service) {
                $cost = $this->formatCostRange($service['min_cost'], $service['max_cost']);
                return "{$service['service_name']}: {$cost}";
            })->implode(', ');
            
            return "• {$providerName} ({$serviceCosts})";
        })->implode('<br>');
        
        return "<div class='bg-gray-50 text-gray-800 border border-gray-200 rounded p-2'>{$providerList}</div>";
    }

    protected function formatServiceWithProviders($serviceName, $isCountryLevel = false, $colorClasses = '')
    {
        $providers = collect();
        
        if ($isCountryLevel) {
            // For Telemedicine, check all cities in the same country
            $countryCities = City::where('country_id', $this->country_id)
                ->whereHas('branchCities.branch.provider', function ($query) {
                    $query->where('status', 'Active');
                })
                ->with(['branchCities.branch' => function ($query) {
                    $query->whereHas('provider', function ($q) {
                        $q->where('status', 'Active');
                    })->with(['provider', 'services']);
                }])
                ->get();
                
            foreach ($countryCities as $city) {
                foreach ($city->branchCities as $branchCity) {
                    $branch = $branchCity->branch;
                    if ($branch && $branch->provider && $branch->provider->status === 'Active') {
                        foreach ($branch->services as $service) {
                            if ($service->name === $serviceName) {
                                $providers->push([
                                    'provider_name' => $branch->provider->name,
                                    'min_cost' => $service->pivot->min_cost,
                                    'max_cost' => $service->pivot->max_cost,
                                    'priority' => $branch->provider->priority ?? 999, // Default priority if not set
                                ]);
                            }
                        }
                    }
                }
            }
        } else {
            // For other services, check only current city
            foreach ($this->branchCities as $branchCity) {
                $branch = $branchCity->branch;
                if ($branch && $branch->provider && $branch->provider->status === 'Active') {
                    foreach ($branch->services as $service) {
                        if ($service->name === $serviceName) {
                            $providers->push([
                                'provider_name' => $branch->provider->name,
                                'min_cost' => $service->pivot->min_cost,
                                'max_cost' => $service->pivot->max_cost,
                                'priority' => $branch->provider->priority ?? 999, // Default priority if not set
                            ]);
                        }
                    }
                }
            }
        }
        
        // Remove duplicates based on provider name and cost
        $providers = $providers->unique(function ($provider) {
            return $provider['provider_name'] . $provider['min_cost'] . $provider['max_cost'];
        });
        
        // Sort by priority (lower number = higher priority)
        $providers = $providers->sortBy('priority');
        
        if ($providers->isEmpty()) {
            return "<span class='font-bold text-red-600'>Missing</span>";
        }
        
        $providerList = $providers->map(function ($provider) {
            $cost = $this->formatCostRange($provider['min_cost'], $provider['max_cost']);
            return "• {$provider['provider_name']} ({$cost})";
        })->implode('<br>');
        
        // Wrap in colored container if color classes provided
        if ($colorClasses) {
            return "<div class='{$colorClasses} border rounded p-2'>{$providerList}</div>";
        }
        
        return $providerList;
    }

    protected function formatCostRange($minCost, $maxCost)
    {
        if (!$minCost && !$maxCost) {
            return 'Price on request';
        }
        
        if ($minCost && $maxCost) {
            if ($minCost == $maxCost) {
                return "€{$minCost}";
            }
            return "€{$minCost} - €{$maxCost}";
        }
        
        if ($minCost) {
            return "From €{$minCost}";
        }
        
        if ($maxCost) {
            return "Up to €{$maxCost}";
        }
        
        return 'Price on request';
    }
}
