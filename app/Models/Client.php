<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Filament\Notifications\Notification;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Log;
use App\Traits\HasContacts;
use App\Traits\NotifiableEntity;

class Client extends Model
{
    use HasFactory, HasContacts, NotifiableEntity;

    protected $fillable = [
        'company_name',
        'type',
        'status',
        'initials',
        'number_requests',
    ];

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
            File::class,
            Patient::class,
            'client_id',
            'patient_id',
            'id',
            'id'
        );
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function notifyClient($type, $data)
    {
        $reason = $this->detectNotificationReason($data);
        $this->sendNotification($reason, $type, $data, 'Client');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'client_id', 'id')->where('type', 'Client');
    }
    public function primaryContact($reason = null)
    {
        $query = $this->contacts();

        if ($reason === 'Invoice' || $reason === 'Balance') {
            $query->where('name', 'Financial');
        } elseif ($reason === 'Appointment') {
            $query->where('name', 'Operation');
        }

        return $query->first();
    }

    public function sendWhatsAppMessage($type, $file)
    {
        try {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $from = 'whatsapp:' . config('services.twilio.whatsapp_from');
            $client = new TwilioClient($sid, $token);

            $contact = $this->primaryContact('Invoice');
            $recipient = $contact ? 'whatsapp:' . $contact->phone_number : null;

            if (!$recipient) {
                Log::error("Twilio WhatsApp Error: No recipient phone number available.");
                return false;
            }

            $message = $client->messages->create(
                $recipient,
                [
                    "from" => $from,
                    "body" => "Your invoice notification message here."
                ]
            );

            Log::info("Twilio WhatsApp Success: Message SID - " . $message->sid);
            return $message->sid;
        } catch (\Exception $e) {
            Log::error("Twilio WhatsApp Error: " . $e->getMessage());
            return false;
        }
    }
}