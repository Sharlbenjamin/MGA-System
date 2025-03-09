<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotifyPatientMailable;

class Patient extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'client_id',
        'dob',
        'gender',
        'country',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'client_id' => 'integer',
        'dob' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
    
    public function country()
    {
        return $this->belongsTo(\App\Models\Country::class, 'country_id', 'id');
    }

    public function firstContact()
    {
        return $this->hasMany(Contact::class, 'patient_id', 'id')->orderBy('created_at', 'asc')->first();
    }

    public function notifyPatient($type, $file)
    {
        $contact = $this->firstContact();

        if (!$contact) {
            return Notification::make()->title('Patient Notification')->body('Patient has no contact information')->danger()->send();
        }

        switch ($contact->preferred_contact) {
            case 'Phone':
                Notification::make()->title('Patient Notification')->body("Please call the patient at: {$contact->phone_number}")->send();
                break;
            case 'Email':
                Mail::to($contact->email)->send(new NotifyPatientMailable($type, $file));
                break;
            case 'Second Email':
                Mail::to($contact->second_email)->send(new NotifyPatientMailable($type, $file));
                break;
        }
    }
}
