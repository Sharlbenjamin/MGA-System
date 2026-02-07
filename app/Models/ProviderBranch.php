<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use App\Mail\NotifyBranchMailable;
use App\Mail\NotifyUsMailable;
use Illuminate\Support\Facades\Auth;
use App\Traits\HasContacts;
use App\Traits\NotifiableEntity;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Schema;
use App\Traits\LogsActivity;

class ProviderBranch extends Model
{
    use HasFactory, HasContacts, NotifiableEntity, LogsActivity;

    protected $fillable = [
        'provider_id', 'branch_name', 'email', 'phone', 'address', 'website', 'city_id', 'province_id', 'status',
        'priority', 'all_country',
        'communication_method', 'emergency', 'pediatrician_emergency', 'dental',
        'pediatrician', 'gynecology', 'urology', 'cardiology', 'ophthalmology',
        'trauma_orthopedics', 'surgery', 'intensive_care', 'obstetrics_delivery',
        'hyperbaric_chamber','gop_contact_id','operation_contact_id','financial_contact_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'provider_id' => 'integer',
        'cities' => 'array',
    ];

    public function getActivityReference(): ?string
    {
        $provider = $this->provider?->name ?? 'Provider #' . $this->provider_id;
        return "{$this->branch_name} ({$provider})";
    }

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

    public function province()
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function gopContact()
    {
        return $this->belongsTo(Contact::class, 'gop_contact_id');
    }

    public function operationContact()
    {
        return $this->belongsTo(Contact::class, 'operation_contact_id');
    }

    public function activeOperationContact()
    {
        return $this->belongsTo(Contact::class, 'operation_contact_id')->where('status', 'Active');
    }

    public function financialContact()
    {
        return $this->belongsTo(Contact::class, 'financial_contact_id');
    }



    public function tasks()
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'branch_id', 'id')->where('type', 'Branch');
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class);
    }

    public function services()
    {
        return $this->belongsToMany(ServiceType::class, 'branch_service')
            ->withPivot(['min_cost', 'max_cost'])
            ->withTimestamps();
    }

    public function notifyBranch($type, $data)
    {
        $reason = $this->detectNotificationReason($data);
        $this->sendNotification($reason, $type, $data, 'Branch');
    }

    /**
     * Get the cost for a specific service type
     */
    public function getCostForService($serviceTypeId, $costType = 'min_cost')
    {
        $service = $this->services()
            ->where('service_type_id', $serviceTypeId)
            ->first();
        
        return $service ? $service->pivot->$costType : null;
    }

    /**
     * Get all costs for a specific service type
     */
    public function getCostsForService($serviceTypeId)
    {
        $service = $this->services()
            ->where('service_type_id', $serviceTypeId)
            ->first();
        
        if (!$service) {
            return null;
        }
        
        return [
            'min_cost' => $service->pivot->min_cost,
            'max_cost' => $service->pivot->max_cost,
        ];
    }

    /**
     * Get the primary contact email (prioritizes direct email, then Operation > GOP > Financial contact)
     */
    public function getPrimaryEmailAttribute()
    {
        if ($this->email) {
            return $this->email;
        }
        
        // Priority: Operation Contact > GOP Contact > Financial Contact
        if ($this->operationContact && $this->operationContact->email) {
            return $this->operationContact->email;
        }
        
        if ($this->gopContact && $this->gopContact->email) {
            return $this->gopContact->email;
        }
        
        if ($this->financialContact && $this->financialContact->email) {
            return $this->financialContact->email;
        }
        
        return null;
    }

    /**
     * Get the primary contact phone (prioritizes direct phone, then Operation > GOP > Financial contact)
     */
    public function getPrimaryPhoneAttribute()
    {
        if ($this->phone) {
            return $this->phone;
        }
        
        // Priority: Operation Contact > GOP Contact > Financial Contact
        if ($this->operationContact && $this->operationContact->phone_number) {
            return $this->operationContact->phone_number;
        }
        
        if ($this->gopContact && $this->gopContact->phone_number) {
            return $this->gopContact->phone_number;
        }
        
        if ($this->financialContact && $this->financialContact->phone_number) {
            return $this->financialContact->phone_number;
        }
        
        return null;
    }



    public function cities()
    {
        return $this->belongsToMany(City::class, 'branch_cities');
    }

    public function branchCities()
    {
        return $this->hasMany(BranchCity::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function bills(): HasManyThrough
    {
        return $this->hasManyThrough(Bill::class, File::class);
    }

    public function transactions()
    {
        return Transaction::where('related_type', 'Branch')->where('related_id', $this->id);
    }


    // Calculated Attributes Calculated Attributes Calculated Attributes Calculated Attributes Calculated Attributes
    public function getFilesCountAttribute()
    {
        return $this->files()->count();
    }

    public function getFilesCancelledCountAttribute()
    {
        return $this->files()->where('status', 'Cancelled')->count();
    }

    public function getFilesAssistedCountAttribute()
    {
        return $this->files()->where('status', 'Assisted')->count();
    }

    public function getBillsTotalNumberAttribute()
    {
        return $this->bills->count();
    }

    public function getBillsTotalAttribute()
    {
        return $this->bills->sum('total_amount');
    }

    public function getBillsTotalPaidAttribute()
    {
        return $this->bills->where('status', 'Paid')->sum('total_amount');
    }

    public function getBillsTotalNumberOutstandingAttribute()
    {
        return $this->bills_total - $this->bills_total_paid;
    }

    public function getBillsTotalNumberPaidAttribute()
    {
        return $this->bills->where('status', 'Paid')->count();
    }

    public function getBillsTotalOutstandingAttribute()
    {
        return $this->bills_total - $this->bills_total_paid;
    }

    public function getTransactionsLastDateAttribute()
    {
        return $this->transactions()->latest()->first()?->bill_date;
    }

    public function getTransactionLastAmountAttribute()
    {
        return $this->transactions()->latest()->first()?->total_amount;
    }
}
