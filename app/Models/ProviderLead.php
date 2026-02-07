<?php

namespace App\Models;

use App\Mail\TailoredMailable;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;
use App\Traits\LogsActivity;

class ProviderLead extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'city_id',
        'service_types',
        'type',
        'provider_id',
        'name',
        'email',
        'phone',
        'communication_method',
        'status',
        'last_contact_date',
        'comment',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'provider_id' => 'integer',
        'last_contact_date' => 'date',
    ];

    public function getActivityReference(): ?string
    {
        $provider = $this->provider?->name ?? 'Provider #' . $this->provider_id;
        return "Lead: " . ($this->name ?? $this->email ?? "#{$this->id}") . " ({$provider})";
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function serviceTypes()
    {
        return $this->belongsToMany(ServiceType::class, 'provider_lead_service_type', 'provider_lead_id', 'service_type_id')
            ->withTimestamps();
    }

    public function interactions()
    {
        return $this->hasMany(Interaction::class);
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public static function sendTailoredMail(array $cc, string $subject, string $body)
    {
        Mail::cc($cc)->send(new TailoredMailable($subject, $body));
        self::whereIn('email', $cc)->update(['last_contact_date' => Carbon::now()]);
        Notification::make()->title('success')->body('Emails sent successfully!');
    }
}