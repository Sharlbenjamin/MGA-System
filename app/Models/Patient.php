<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Filament\Notifications\Notification;
use App\Traits\HasContacts;
use App\Traits\NotifiableEntity;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    public function getFilesCountAttribute()
    {
        return $this->files()->count();
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function bills(): HasManyThrough
    {
        return $this->hasManyThrough(Bill::class, File::class);
    }

    public function notifyPatient($type, $data)
    {
        $reason = $this->detectNotificationReason($data);
        $this->sendNotification($reason, $type, $data, 'Patient');
    }






    // Financial Calculations
    public function getInvoicesTotalAttribute()
    {
        return $this->invoices()->sum('total_amount');
    }

    public function getInvoicesTotalPaidAttribute()
    {
        return $this->invoices()->sum('paid_amount');
    }

    public function getInvoicesTotalOutstandingAttribute()
    {
        return $this->invoices_total - $this->invoices_total_paid;
    }

    public function getBillsTotalAttribute()
    {
        return $this->bills()->sum('total_amount');
    }

    public function getBillsTotalPaidAttribute()
    {
        return $this->bills()->sum('paid_amount');
    }

    public function getBillsTotalOutstandingAttribute()
    {
        return $this->bills_total - $this->bills_total_paid;
    }

    public function getProfitTotalAttribute()
    {
        return $this->invoices_total - $this->bills_total;
    }

    public function getProfitTotalPaidAttribute()
    {
        return $this->invoices_total_paid - $this->bills_total_paid;
    }

    public function getProfitTotalOutstandingAttribute()
    {
        return $this->invoices_total_outstanding - $this->bills_total_outstanding;
    }

    /**
     * Find similar patients by name and client
     */
    public static function findSimilar($name, $clientId = null, $limit = 5)
    {
        $query = static::query()
            ->with('client')
            ->where('name', 'like', '%' . $name . '%');

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Check if a patient with the same name and client already exists
     */
    public static function findDuplicate($name, $clientId)
    {
        return static::where('name', $name)
            ->where('client_id', $clientId)
            ->first();
    }
}