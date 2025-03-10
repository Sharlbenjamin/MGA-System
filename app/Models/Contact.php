<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contact extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'client_id',
        'provider_id',
        'branch_id',
        'patient_id',
        'name',
        'title',
        'email',
        'second_email',
        'phone_number',
        'second_phone',
        'country_id',
        'city_id',
        'address',
        'preferred_contact',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'country_id' => 'integer',
        'city_id' => 'integer',
    ];

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

    // In App\Models\Contact.php

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
            'WhatsApp' => ['type' => 'whatsapp', 'value' => $this->first_whatsapp],
            'Second WhatsApp' => ['type' => 'whatsapp', 'value' => $this->second_whatsapp],
            default => null,
        };
    }
}
