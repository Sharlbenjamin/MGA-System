<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Filament\Notifications\Notification;
use App\Notifications\UserNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotifyClientMailable;
use Filament\Facades\Filament;
use Twilio\Rest\Client as TwilioClient; 
use App\Models\File;

class Client extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_name',
        'type',
        'status',
        'initials',
        'number_requests',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function files(): HasManyThrough
    {
        return $this->hasManyThrough(
            File::class,  // Final model
            Patient::class,  // Intermediate model
            'client_id',     // Foreign key on patients table
            'patient_id',    // Foreign key on requests table
            'id',            // Local key on clients table
            'id'             // Local key on patients table
        );
    }

    public function firstContact()
    {
        return $this->hasMany(Contact::class, 'client_id', 'id')->orderBy('created_at', 'asc')->first();
    }

    public function latestLead()
    {
        return $this->hasOne(Lead::class)->latestOfMany('last_contact_date');
    }

    public function notifyClient($type, $file)
    {
        $contact = $this->firstContact();
        if (!$contact or $contact->status != 'Active') {
            Notification::make()->title('This Client does not have a Contact')->danger()->send();
            return;
        }
        
        switch ($contact->preferred_contact) {
            case 'Phone':
                if (empty($contact->phone_number)) {
                    Notification::make()->title('This Client does not have a Phone number')->danger()->send();
                } else {
                    Notification::make()->title('Client Notification')->body("The Clients needs confirmation by phone")->success()->send();
                }
                break;
            case 'Second Phone':
                if (empty($contact->second_phone)) {
                    Notification::make()->title('This Client does not have a Second Phone number')->danger()->send();
                } else {
                    Notification::make()->title('Client Notification')->body("The Clients needs confirmation by phone")->send();
                }
                break;
            case 'Email':
                if (empty($contact->email)) {
                    Notification::make()->title('Client Notification')->body("This Client is missing an Email")->send();
                } else {
                    Mail::to($contact->email)->send(new NotifyClientMailable($type, $file));
                }
                break;
            case 'Second Email':
                if (empty($contact->second_email)) {
                    Notification::make()->title('Client Notification')->body("This Client is missing a Second Email")->send();
                } else {
                    Mail::to($contact->second_email)->send(new NotifyClientMailable($type, $file));
                }
                break;
            case 'first_whatsapp':
                if (empty($contact->phone_number)) {
                    Notification::make()->title('Client Notification')->body("This Client is missing a Phone Number")->send();
                } else {
                    // Send via whats app
                   // $this->sendWhatsAppMessage($type, $file);
                   Notification::make()->title('Client Notification')->body("Whats app notificaitons are not active yet")->send();
                }
                break;
            case 'second_whatsapp':
                if (empty($contact->second_phone)) {
                    Notification::make()->title('Client Notification')->body("This Client is missing a Second Phone Number")->send();
                } else {
                    // Send via whats app
                   // $this->sendWhatsAppMessage($type, $file);
                   Notification::make()->title('Client Notification')->body("Whats app notificaitons are not active yet")->send();

                }
                break;
        }
    }

    public function sendWhatsAppMessage($type, $file)
    {
        $sid   = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $client = new TwilioClient($sid, $token);

        // Format the recipient's phone number for WhatsApp (include country code)
        $recipient = 'whatsapp:' . $this->firstContact->phone_number;
        $sender    = 'whatsapp:' . config('services.twilio.whatsapp_from');


        $patient_name = $file->patient->name;
        $mga_reference = $file->mga_reference;
        $message = match ($this->type) {
            'file_created' => "Hello, this is a message from MGA. We have created a new file for {$patient_name} with the reference number {$mga_reference}.",
            'file_cancelled' => "Hello, this is a message from MGA. We have cancelled the file for {$patient_name} with the reference number {$mga_reference}.",
            'confirm' => 'Hello, this is a message from MGA. We have confirmed the appointment for the patient.',
            // 'available' => 'emails.available-appointments-mail',
            // 'reminder' => 'emails.reminder-appointment-mail',
            //'assisted' => 'emails.patient-assisted-mail',
        };

        $client->messages->create($recipient, [
            'from' => $sender,
            'body' => $message,
        ]);
    }
}

