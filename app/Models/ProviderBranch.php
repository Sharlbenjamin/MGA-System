<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;
use App\Mail\AppointmentNotificationMail;
use App\Mail\NotifyBranchMailable;
use App\Mail\NotifyUsMailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class ProviderBranch extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'provider_id',
        'branch_name',
        'city_id',
        'province_id',
        'status',
        'priority',
        'service_type_id',
        'communication_method',
        'day_cost',
        'night_cost',
        'weekend_cost',
        'weekend_night_cost',
        'emergency',
        'pediatrician_emergency',
        'dental',
        'pediatrician',
        'gynecology',
        'urology',
        'cardiology',
        'ophthalmology',
        'trauma_orthopedics',
        'surgery',
        'intensive_care',
        'obstetrics_delivery',
        'hyperbaric_chamber',
        
        
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'provider_id' => 'integer',
        'service_type_id' => 'integer',
        'day_cost' => 'decimal:2',
        'night_cost' => 'decimal:2',
        'weekend_cost' => 'decimal:2',
        'weekend_night_cost' => 'decimal:2',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }

    public function firstContact()
    {
        return $this->hasMany(Contact::class, 'branch_id', 'id')->orderBy('created_at', 'asc')->first();
    }

    public function notifyBranch($type, $appointment)
    {
        $contact = $this->firstContact();
        if (!$contact) {
            Notification::make()->title('No Provider Contact')->body("No contact information found for this branch")->danger()->send();
            return;
        }
        match ($contact->preferred_contact) {
            'Phone', 'Second Phone' => $this->notifyByPhone($type, $appointment),
            'first_whatsapp', 'second_whatsapp' => $this->notifyByWhatsapp($contact),
            'Email', 'Second Email' => $this->notifyByEmail($type, $appointment),
        };
    }

    private function notifyByPhone($type, $appointment)
    {
        $toMail = "mga.operation@medguarda.com";
        Mail::to($toMail)->send(new NotifyUsMailable($type, $appointment));
    }

    private function notifyByEmail($type, $appointment)
    {
        $toMail = match ($this->firstContact()->preferred_contact){
            'Email', => $this->firstContact()->email,
            'Second Email' => $this->firstContact()->second_email,
        };

        Mail::to($toMail)->send(new NotifyBranchMailable($type, $appointment));
    }

    public function notifyByWhatsapp($contact)
    {
        return Notification::make()->title('Contact Provider')->body("wWhatsapp notifications are not active yet")->send();
    }
}
