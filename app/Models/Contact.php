<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Contact extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['type','client_id','provider_id','branch_id','patient_id','name','title','email','second_email','phone_number','second_phone','country_id','city_id','address','preferred_contact','status',];

    protected $casts = [
        'country_id' => 'integer',
        'city_id' => 'integer',
        'client_id' => 'integer',
        'provider_id' => 'integer',
        'branch_id' => 'integer',
        'patient_id' => 'integer',
    ];

    // Get name as string for display (kept for backward compatibility)
    public function getNameStringAttribute()
    {
        return $this->name;
    }

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(ProviderBranch::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'gop_contact_id', 'id');
    }

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class, 'gop_contact_id', 'id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(ProviderBranch::class, 'gop_contact_id', 'id');
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'gop_contact_id', 'id');
    }

    public function getEntityNameAttribute(): string
    {
        switch ($this->type) {
            case 'Client':
                $entity = \App\Models\Client::find($this->client_id);
                return $entity ? $entity->company_name : '';
            case 'Provider':
                $entity = \App\Models\Provider::find($this->provider_id);
                return $entity ? $entity->name : '';
            case 'Branch':
                $entity = \App\Models\ProviderBranch::find($this->branch_id);
                return $entity ? $entity->branch_name : '';
            case 'Patient':
                 $entity = \App\Models\Patient::find($this->patient_id);
                 return $entity ? $entity->name : '';
            default:
                return '';
        }
    }

    public function preferredCommunication()
    {
        return match ($this->preferred_contact) {
            'Phone' => ['type' => 'phone', 'value' => $this->phone_number],
            'Second Phone' => ['type' => 'phone', 'value' => $this->second_phone],
            'Email' => ['type' => 'email', 'value' => $this->email],
            'Second Email' => ['type' => 'email', 'value' => $this->second_email],
            'first_whatsapp' => ['type' => 'whatsapp', 'value' => $this->first_whatsapp],
            'second_whatsapp' => ['type' => 'whatsapp', 'value' => $this->second_whatsapp],
            default => null,
        };
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    /**
     * Get the route key value.
     */
    public function getRouteKey()
    {
        $key = $this->getAttribute($this->getRouteKeyName());
        // Ensure we return the key as a string for consistency
        return (string) $key;
    }

    /**
     * Find the model instance for the given route key value.
     */
    public static function findByRouteKey($value)
    {
        // First try the standard approach
        $model = static::find($value);
        if ($model) {
            return $model;
        }

        // If that fails and it's numeric, try to find by string comparison
        if (is_numeric($value)) {
            $model = static::where('id', '=', $value)->first();
            if ($model) {
                return $model;
            }
        }

        // Try to find by string comparison for any value
        $model = static::where('id', '=', (string) $value)->first();
        if ($model) {
            return $model;
        }

        // If still not found and it's numeric, try a more aggressive search
        if (is_numeric($value)) {
            // Try to find by LIKE for partial matches
            $model = static::where('id', 'LIKE', '%' . $value . '%')->first();
            if ($model) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Resolve the model binding for the route.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // Use our custom method to handle both integer and UUID formats
        $contact = static::findByRouteKey($value);
        if ($contact) {
            return $contact;
        }
        
        // Fallback to default behavior
        return parent::resolveRouteBinding($value, $field);
    }
}
