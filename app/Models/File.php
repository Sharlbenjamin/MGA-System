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

    protected $fillable = ['status','mga_reference','patient_id','client_reference','country_id','city_id','service_type_id','provider_branch_id','service_date','service_time','address','symptoms','diagnosis','contact_patient', 'google_drive_link', 'email', 'phone'];

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

    public function gopInTotal()
    {
        return $this->gops()->where('type', 'In')->sum('amount');
    }

    public function gopOutTotal()
    {
        return $this->gops()->where('type', 'Out')->sum('amount');
    }

    public function gopTotal()
    {
        return $this->gops()->sum('amount');
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
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function billsTotal()
    {
        return $this->bills()->sum('total_amount');
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
                $query->whereJsonContains('service_types', $serviceTypeName)
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
                ->whereJsonContains('service_types', $serviceTypeName)
                ->orderBy('priority', 'asc')
                ->get();

            return [
                'cityBranches' => $allBranches,
                'allBranches' => $allBranches,
            ];
        }

        // If no country is assigned, show all branches with matching service type
        if (!$this->country_id) {
            $allBranches = \App\Models\ProviderBranch::query()
                ->where('status', 'Active')
                ->whereJsonContains('service_types', $serviceTypeName)
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
            ->whereJsonContains('service_types', $serviceTypeName)
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
            ->whereJsonContains('service_types', $serviceTypeName)
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
        $processedCount = 0;
        $errorCount = 0;

        foreach ($file->appointments as $appointment) {
            try {
                // Email notifications are now handled manually through the ViewFile interface
                // to prevent duplicate emails and ensure proper control
                $processedCount++;
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to process appointment notification', [
                    'appointment_id' => $appointment->id,
                    'file_id' => $file->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Log in comments
        $file->comments()->create([
            'user_id' => Auth::id(),
            'content' => "Appointment requests processed for file. Processed: {$processedCount}, Errors: {$errorCount}"
        ]);

        return [
            'processed' => $processedCount,
            'errors' => $errorCount
        ];
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

    /**
     * Confirm telemedicine appointment for this file
     * This method finds the latest requested appointment and confirms it
     */
    public function confirmTelemedicineAppointment()
    {
        // Find the latest requested appointment for this file
        $latestAppointment = $this->appointments()
            ->where('status', 'Requested')
            ->latest()
            ->first();

        if (!$latestAppointment) {
            throw new \Exception('No requested appointment found for this file.');
        }

        // Confirm the appointment (this will trigger the updated event in Appointment model)
        $latestAppointment->update(['status' => 'Confirmed']);

        // Add a comment to track this action
        $this->comments()->create([
            'user_id' => Auth::id(),
            'content' => 'Telemedicine appointment confirmed manually via "Confirm Telemedicine" button.'
        ]);

        return $latestAppointment;
    }

    public static function generateMGAReference($id, $type)
    {
        if (!$id) return 'MG000XXX';

        if ($type == 'client') {
            $client = Client::find($id);
            if (!$client) return 'MG000XXX';

            return sprintf('MG%03d%s', $client->files()->count() + 1, $client->initials ?? '');
        } else {
            $patient = Patient::find($id);
            if (!$patient) return 'MG000XXX';

            return sprintf('MG%03d%s', $patient->client->files()->count() + 1, $patient->client->initials ?? '');
        }
    }

    // Attributes   Attributes   Attributes   Attributes   Attributes   Attributes   Attributes   Attributes

    public function getNameAttribute()
    {
        $patientName = $this->patient ? $this->patient->name : 'Unknown Patient';
        return $this->mga_reference . ' - ' . $patientName;
    }

    public function getInvoiceAmountAttribute()
    {
        return $this->invoices()->sum('total_amount');
    }

    public function getBillAmountAttribute()
    {
        return $this->bills()->sum('total_amount');
    }
}
