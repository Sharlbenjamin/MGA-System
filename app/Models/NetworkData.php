<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkData extends Model
{
    protected $table = 'cities'; // We'll use cities as base table
    
    protected $fillable = [
        'id',
        'name',
        'country_id',
    ];

    protected $appends = [
        'country',
        'city',
        'services',
        'telemedicine',
        'house_visit',
        'dental',
        'clinic',
        'cost',
    ];

    public function getCountryAttribute()
    {
        return $this->country()->first()?->name ?? 'Unknown';
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
        return $this->formatServiceAvailability('Telemedicine');
    }

    public function getHouseVisitAttribute()
    {
        return $this->formatServiceAvailability('House Call');
    }

    public function getDentalAttribute()
    {
        return $this->formatServiceAvailability('Dental Clinic');
    }

    public function getClinicAttribute()
    {
        return $this->formatServiceAvailability('Clinic Visit');
    }

    public function getCostAttribute()
    {
        $services = $this->services;
        
        if ($services->isEmpty()) {
            return "<span class='text-red-600 font-medium'>No Services</span>";
        }
        
        $costs = $services->map(function ($service) {
            return $this->formatCostRange($service['min_cost'], $service['max_cost']);
        })->filter()->unique();
        
        return $costs->implode('<br>');
    }

    protected function formatServiceAvailability($serviceName)
    {
        $hasService = $this->services->contains('service_name', $serviceName);
        
        if ($hasService) {
            $service = $this->services->firstWhere('service_name', $serviceName);
            $cost = $this->formatCostRange($service['min_cost'], $service['max_cost']);
            return "<span class='text-green-600 font-medium'>Available</span><br><small class='text-gray-500'>{$cost}</small>";
        }
        
        return "<span class='text-red-600 font-medium'>Missing</span>";
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

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function branchCities()
    {
        return $this->hasMany(BranchCity::class);
    }
}
