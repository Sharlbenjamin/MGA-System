<?php

namespace App\Models;

use App\Mail\TailoredMailable;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use App\Traits\LogsActivity;

class Lead extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'client_id',
        'email',
        'first_name',
        'status',
        'last_contact_date',
        'linked_in',
        'phone',
        'contact_method',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'client_id' => 'integer',
        'last_contact_date' => 'date',
    ];

    public function getActivityReference(): ?string
    {
        $client = $this->client?->company_name ?? 'Client #' . $this->client_id;
        return "Lead: " . ($this->first_name ?? $this->email ?? "#{$this->id}") . " ({$client})";
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Leads whose client is still in the outreach pipeline.
     * Active, On Hold, and Rejected clients are handled elsewhere.
     */
    public function scopeInClientPipeline(Builder $query): Builder
    {
        return $query->whereHas('client', function (Builder $clientQuery) {
            $clientQuery->whereRaw(
                'LOWER(clients.status) NOT IN (?, ?, ?)',
                ['active', 'on hold', 'rejected'],
            );
        });
    }

    public function scopeExcludingRejectedClients(Builder $query): Builder
    {
        return $query->whereHas('client', function (Builder $clientQuery) {
            $clientQuery->whereRaw('LOWER(clients.status) != ?', ['rejected']);
        });
    }

    /**
     * @param  array<int, string>  $statuses
     */
    public function scopeForClientStatuses(Builder $query, array $statuses): Builder
    {
        $normalized = [];

        foreach ($statuses as $status) {
            $status = strtolower(trim((string) $status));

            if ($status !== '') {
                $normalized[] = $status;
            }
        }

        $normalized = array_values(array_unique($normalized));

        if ($normalized === []) {
            return $query;
        }

        $placeholders = implode(', ', array_fill(0, count($normalized), '?'));

        return $query->whereHas('client', function (Builder $clientQuery) use ($normalized, $placeholders) {
            $clientQuery->whereRaw('LOWER(clients.status) IN ('.$placeholders.')', $normalized);
        });
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

    public static function boot()
    {
        parent::boot();
        static::updated(function ($lead) {
            // if lead status is Error or Missing Information
            if($lead->client->leads->where('status', 'Error')->count() > 0) {
                $lead->client->update([
                    'status' => 'Searching'
                ]);
            }
        });
    }
}
