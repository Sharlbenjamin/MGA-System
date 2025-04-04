<?php

namespace App\Models;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\ConferenceSolutionKey;
use Illuminate\Support\Facades\Log;
use App\Services\GoogleCalendar as GoogleCalendarService;
use App\Services\GoogleMeetService;

class File extends Model
{
    use HasFactory;

    protected $fillable = ['status','mga_reference','patient_id','client_reference','country_id','city_id','service_type_id','provider_branch_id','service_date','service_time','address','symptoms','diagnosis','contact_patient',];

    protected $casts = ['id' => 'integer','patient_id' => 'integer','country_id' => 'integer','city_id' => 'integer','service_type_id' => 'integer','provider_branch_id' => 'integer','service_date' => 'date',];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function getNameAttribute()
    {
        return $this->mga_reference . ' - ' . $this->patient->name;
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'file_id', 'id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }


    public function providerBranch(): BelongsTo
    {
        return $this->belongsTo(ProviderBranch::class);
    }

    public function medicalReports(): HasMany
    {
        return $this->hasMany(MedicalReport::class); // Ensure 'file_id' is the correct foreign key
    }

    public function gops(): HasMany
    {
        return $this->hasMany(Gop::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function provider(): HasOneThrough
    {
        return $this->hasOneThrough(
            Provider::class,        // Final model (Provider)
            ProviderBranch::class,  // Intermediate model (ProviderBranch)
            'provider_id',          // Foreign key on ProviderBranch (ProviderBranch.provider_id)
            'id',                   // Foreign key on Provider (Provider.id)
            'provider_branch_id',    // Local key on DrFiles (DrFiles.provider_branch_id)
            'id'                    // Local key on ProviderBranch (ProviderBranch.id)
        );
    }
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function fileBranches()
    {
        $serviceTypeName = ServiceType::find($this->service_type_id)?->name;

        return \App\Models\ProviderBranch::query()
            ->when($serviceTypeName, fn ($query) =>
                $query->where('service_types', 'like', '%' . $serviceTypeName . '%')
            )
            ->when($this->city_id, fn ($query) =>
                $query->where('city_id', $this->city_id)
            )->orderBy('priority', 'asc')->get();
    }

    public function requestAppointments($file)
    {
        foreach ($file->appointments as $appointment) {
            if ($appointment->status === 'Cancelled') {
                $appointment->providerBranch->notifyBranch('appointment_cancelled', $appointment);
                continue;
            }

            if ($appointment->isUpdated()) {
                $appointment->providerBranch->notifyBranch('appointment_updated', $appointment);
            } else {
                $appointment->providerBranch->notifyBranch('appointment_created', $appointment);
            }
        }
        // Log in comments
        $file->comments()->create([
            'user_id' => Auth::id(),
            'content' => "Appointment requests processed for file."
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($file) {
            // Delete nested relationships first
            foreach($file->prescriptions as $prescription) {
                $prescription->drugs()->delete();
            }
            // Delete all related records
            $file->prescriptions()->delete();
            $file->gops()->delete();
            $file->medicalReports()->delete();
            $file->comments()->delete();
            $file->appointments()->delete();
            $file->tasks()->delete();
        });

        static::created(function ($file) {
            if($file->contact_patient === 'Client'){
                $file->patient->client->notifyClient('file_created', $file);
            }elseif($file->contact_patient === 'Ask'){
                $file->patient->client->notifyClient('ask_client', $file);

            }else{
                $file->patient->notifyPatient('file_created', $file);
            }
        });

        static::updated(function ($file) {
            if ($file->isDirty('status')) {
                if (!$file->mga_reference) {
                    return;
                }

                match ($file->status) {
                    'Assisted' => $file->patient->client->notifyClient('file_assisted', $file),
                    'Available' => $file->contact_patient === 'Client' ? $file->patient->client->notifyClient('file_available', $file) : $file->patient->notifyPatient('file_available', $file),
                    'Hold' => $file->patient->client->notifyClient('file_hold', $file),
                    'Cancelled' => $file->patient->client->notifyClient('file_cancelled', $file),
                    'Requesting GOP' => $file->patient->client->notifyClient('requesting_gop', $file),
                    //'Void' => $file->patient->client->notifyClient('file_void', $file),
                    default => null,
                };
            }
        });
    }

    public function cancelAppointments($file)
    {
        foreach ($file->appointments as $appointment) {
            $appointment->status = 'Cancelled';
            $appointment->save();
        }
    }

    public function generateGoogleMeetLink()
    {
        return app(GoogleMeetService::class)->generateMeetLink($this);
    }

    public static function generateMGAReference($patientId)
    {
        if (!$patientId) return 'MG000XXX';

        $patient = Patient::find($patientId);
        if (!$patient || !$patient->client) return 'MG000XXX';

        return sprintf('MG%03d%s', $patient->client->files()->count() + 1, $patient->client->initials ?? '');
    }
}
