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
use App\Services\GoogleDriveFolderService;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class File extends Model
{
    use HasFactory;

    protected $fillable = ['status','mga_reference','patient_id','client_reference','country_id','city_id','service_type_id','provider_branch_id','service_date','service_time','address','symptoms','diagnosis','contact_patient', 'google_drive_link'];

    protected $casts = ['id' => 'integer','patient_id' => 'integer','country_id' => 'integer','city_id' => 'integer','service_type_id' => 'integer','provider_branch_id' => 'integer','service_date' => 'date', ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
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

    public function serviceType()
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

    public function createInvoice()
    {
        $invoice = new Invoice();
        $invoice->patient_id = $this->patient_id;
        $invoice->name = Invoice::generateInvoiceNumber($invoice);
        $invoice->due_date = now()->addDays(60);
        $invoice->status = 'Draft';
        $invoice->save();
    }
    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(Invoice::class, Patient::class, 'id', 'patient_id', 'id', 'id');
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
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
            )
            ->when($this->city_id, fn ($query) =>
                $query->where('province_id', $this->city->province_id)
            )
            ->where('status', 'Active')->orderBy('priority', 'asc')->get();
    }

    public function availableBranches()
{
    $serviceTypeName = $this->serviceType?->name;

    // If service type is 2, ignore country/city filters
    if ($this->service_type_id == 2) {
        $allBranches = \App\Models\ProviderBranch::query()
            ->where('status', 'Active')
            ->where('service_types', 'like', '%' . $serviceTypeName . '%')
            ->orderBy('priority', 'asc')
            ->get();

        return [
            'cityBranches' => $allBranches,
            'allBranches' => $allBranches,
        ];
    }

    // Filter branches by city (direct or via pivot) or all_country
    $cityBranches = \App\Models\ProviderBranch::query()
        ->where('status', 'Active')
        ->where('service_types', 'like', '%' . $serviceTypeName . '%')
        ->whereHas('provider', fn ($q) => $q->where('country_id', $this->country_id))
        ->where(function ($q) {
            $q->where('all_country', true)
              ->orWhereHas('branchCities', fn ($q) => $q->where('city_id', $this->city_id));
        })
        ->orderBy('priority', 'asc')
        ->get();

    // Filter branches by province or all_country
    $provinceBranches = \App\Models\ProviderBranch::query()
        ->where('status', 'Active')
        ->where('service_types', 'like', '%' . $serviceTypeName . '%')
        ->whereHas('provider', fn ($q) => $q->where('country_id', $this->country_id))
        ->where(function ($q) {
            $q->where('all_country', true)
              ->orWhere('province_id', $this->city?->province_id);
        })
        ->orderBy('priority', 'asc')
        ->get();

    return [
        'cityBranches' => $cityBranches,
        'allBranches' => $provinceBranches->merge($cityBranches)->unique('id'),
    ];
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
            app(GoogleDriveFolderService::class)->generateGoogleDriveFolder($file);
        });

        static::updated(function ($file) {
            if ($file->isDirty('status')) {
                if (!$file->mga_reference) {
                    return;
                }
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

    // Attributes   Attributes   Attributes   Attributes   Attributes   Attributes   Attributes   Attributes

    public function getNameAttribute()
    {
        return $this->mga_reference . ' - ' . $this->patient->name;
    }

    public function getInvoiceAmountAttribute()
    {
        return $this->patient->invoices()->sum('total_amount');
    }

    public function getBillAmountAttribute()
    {
        return $this->bills()->sum('total_amount');
    }
}
