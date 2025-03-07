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

class File extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'status',
        'mga_reference',
        'patient_id',
        'client_reference',
        'country_id',
        'city_id',
        'service_type_id',
        'provider_branch_id',
        'service_date',
        'service_time',
        'address',
        'symptoms',
        'diagnosis',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'patient_id' => 'integer',
        'country_id' => 'integer',
        'city_id' => 'integer',
        'service_type_id' => 'integer',
        'provider_id' => 'integer',
        'provider_branch_id' => 'integer',
        'service_date' => 'date',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
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
    
    public function fileBranches()
    {
        return \App\Models\ProviderBranch::query()
            ->when($this->service_type_id, fn ($query) => 
                $query->where('service_type_id', $this->service_type_id)
            )
            ->when($this->city_id, fn ($query) => 
                $query->where('city_id', $this->city_id)
            )
            ->orderBy('priority', 'asc')
            ->get(); // ✅ Ensure we retrieve a collection
    }

    public function requestAppointments($file)
    {
        foreach ($file->appointments as $appointment) {
            if ($appointment->status === 'Cancelled') {
                $appointment->providerBranch->notifyBranch('cancel');
                continue;
            }

            if ($appointment->isUpdated()) {
                $appointment->providerBranch->notifyBranch('update');
            } else {
                $appointment->providerBranch->notifyBranch('new');
            }
        }

        // Notify the patient and client
        $file->appointments()->latest()->first()?->patient->notifyPatient('available');
        $file->client->notifyClient('available');

        // Log in comments
        $file->comments()->create([
            'user_id' => Auth::id(),
            'content' => "Appointment requests processed for file."
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($file) {
            $file->patient->client->notifyClient('file_created', $file);
        });

        static::updated(function ($file) {
            switch ($file->status) {
                case 'Handling':
                    $file->patient->client->notifyClient('file_handling', $file);
                    break;
                case 'Assisted':
                    $file->patient->client->notifyClient('assisted', $file);
                    break;
                case 'Hold':
                    $file->patient->client->notifyClient('file_hold', $file);
                    break;
                case 'Void':
                    $file->patient->client->notifyClient('file_void', $file);
                    break;
            }
        });
    }
}
