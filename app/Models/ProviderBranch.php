<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;
use App\Mail\NotifyBranchMailable;
use App\Mail\NotifyUsMailable;
use Illuminate\Support\Facades\Auth;
use App\Traits\HasContacts;
use App\Traits\NotifiableEntity;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ProviderBranch extends Model
{
    use HasFactory, HasContacts, NotifiableEntity;

    protected $fillable = [
        'provider_id', 'branch_name', 'city_id', 'province_id', 'status',
        'priority', 'service_types', 'all_country',
        'communication_method', 'day_cost', 'night_cost', 'weekend_cost',
        'weekend_night_cost', 'emergency', 'pediatrician_emergency', 'dental',
        'pediatrician', 'gynecology', 'urology', 'cardiology', 'ophthalmology',
        'trauma_orthopedics', 'surgery', 'intensive_care', 'obstetrics_delivery',
        'hyperbaric_chamber','gop_contact_id','operation_contact_id','financial_contact_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'provider_id' => 'integer',
        'service_types' => 'array',
        'cities' => 'array',
        'day_cost' => 'decimal:2',
        'night_cost' => 'decimal:2',
        'weekend_cost' => 'decimal:2',
        'weekend_night_cost' => 'decimal:2'
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function getNameAttribute()
    {
        return $this->branch_name;
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'branch_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function gopContact()
    {
        return $this->belongsTo(Contact::class, 'gop_contact_id');
    }

    public function operationContact()
    {
        return $this->belongsTo(Contact::class, 'operation_contact_id')->where('status', 'Active');
    }

    public function financialContact()
    {
        return $this->belongsTo(Contact::class, 'financial_contact_id');
    }

    public function serviceTypes()
    {
        return $this->belongsToMany(ServiceType::class, null, 'service_types')
            ->using(function ($value) {
                return ServiceType::whereIn('name', explode(',', $value))->get();
            });
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'branch_id', 'id')->where('type', 'Branch');
    }

    public function notifyBranch($type, $data)
    {
        $reason = $this->detectNotificationReason($data);
        $this->sendNotification($reason, $type, $data, 'Branch');
    }

    public function cities()
    {
        return $this->belongsToMany(City::class, 'branch_cities');
    }

    public function branchCities()
    {
        return $this->hasMany(BranchCity::class);
    }
}
