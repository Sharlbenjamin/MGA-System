<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Filament\Notifications\Notification;
use App\Traits\HasContacts;
use App\Traits\NotifiableEntity;

class Patient extends Model
{
    use HasFactory, HasContacts, NotifiableEntity;

    protected $fillable = ['name','client_id','dob','gender','country','gop_contact_id','operation_contact_id','financial_contact_id',];

    protected $casts = [
        'id' => 'integer',
        'client_id' => 'integer',
        'dob' => 'date',
    ];

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function country()
    {
        return $this->belongsTo(\App\Models\Country::class, 'country_id', 'id');
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'taskable');
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
        return $this->belongsTo(Contact::class, 'financial_contact_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'patient_id', 'id')->where('type', 'Patient');
    }

    public function notifyPatient($type, $data)
    {
        $reason = $this->detectNotificationReason($data);
        $this->sendNotification($reason, $type, $data, 'Patient');
    }

}