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
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Client extends Model
{
    use HasFactory, HasContacts, NotifiableEntity;

    protected $fillable = ['company_name','type','status','initials','number_requests','gop_contact_id','operation_contact_id','financial_contact_id',];

    protected $casts = [
        'id' => 'integer',
    ];

    public function getNameAttribute()
    {
        return $this->company_name;
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function gopContact()
    {
        return $this->belongsTo(Contact::class, 'gop_contact_id');
    }

    public function operationContact()
    {
        return $this->belongsTo(Contact::class, 'operation_contact_id');
    }

    public function financialContact()
    {
        return $this->contacts()->where('name', 'like', '%Financial%')->first();
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
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

    public function transactions()
    {
        return Transaction::where('related_type', 'Client')->where('related_id', $this->id);
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





    // Over View Calculations

    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, Patient::class);
    }





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

    public function getInvoicesTotalNumberAttribute()
    {
        return $this->invoices()->count();
    }

    public function getInvoicesTotalAttribute()
    {
        return $this->invoices()->sum('total_amount');
    }

    public function getInvoicesTotalPaidAttribute()
    {
        return $this->invoices()->sum('paid_amount');
    }

    public function getInvoicesTotalNumberOutstandingAttribute()
    {
        return $this->invoices()->where('status', '!=', 'Paid')->count();
    }

    public function getInvoicesTotalNumberPaidAttribute()
    {
        return $this->invoices()->where('status', 'Paid')->count();
    }

    public function getInvoicesTotalOutstandingAttribute()
    {
        return $this->invoices_total - $this->invoices_total_paid;
    }

    public function getTransactionsLastDateAttribute()
    {
        return $this->transactions()->latest()->first()?->date;
    }


    public function getTransactionLastAmountAttribute()
    {
        return $this->transactions()->latest()->first()?->amount;
    }

}