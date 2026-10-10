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
     * Leads whose client is still in the outreach pipeline (Potential clients).
     * Active and Inactive clients are handled elsewhere.
     */
    public function scopeInClientPipeline(Builder $query): Builder
    {
        return $query->whereHas('client', function (Builder $clientQuery) {
            $clientQuery->inStatusGroup(Client::STATUS_GROUP_POTENTIAL);
        });
    }

    /**
     * Client statuses kept out of the client-leads list until that status is filtered.
     * Hides Active and Inactive groups; Potential clients remain visible by default.
     */
    public function scopeHiddenFromCrmUntilFiltered(Builder $query): Builder
    {
        return $query->whereHas('client', function (Builder $clientQuery): void {
            $clientQuery->inStatusGroup(Client::STATUS_GROUP_POTENTIAL);
        });
    }

    /**
     * CRM leads: not Error, and the client is in the Potential group.
     */
    public function scopeInClientLeadList(Builder $query): Builder
    {
        return $query
            ->whereRaw('LOWER(leads.status) != ?', ['error'])
            ->hiddenFromCrmUntilFiltered();
    }

    public function scopeExcludingRejectedClients(Builder $query): Builder
    {
        return $query->whereHas('client', function (Builder $clientQuery) {
            $hidden = array_merge(
                Client::normalizedStatusesForGroup(Client::STATUS_GROUP_ACTIVE),
                Client::normalizedStatusesForGroup(Client::STATUS_GROUP_INACTIVE),
            );
            $placeholders = implode(', ', array_fill(0, count($hidden), '?'));

            $clientQuery->whereRaw('LOWER(clients.status) NOT IN ('.$placeholders.')', $hidden);
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

    public function isReminderEmailStatus(?string $status): bool
    {
        return str_contains(strtolower((string) $status), 'reminder');
    }

    public function consecutiveReminderEmailsSent(): int
    {
        $count = 0;

        $interactions = $this->interactions()
            ->where('method', 'Email')
            ->orderByDesc('interaction_date')
            ->orderByDesc('id')
            ->get(['status']);

        foreach ($interactions as $interaction) {
            if (! $this->isReminderEmailStatus($interaction->status)) {
                break;
            }

            $count++;
        }

        return $count;
    }

    public function statusAfterOutgoingEmail(string $intendedStatus): string
    {
        if ($this->isReminderEmailStatus($this->status) && $this->consecutiveReminderEmailsSent() >= 3) {
            return 'No Reply';
        }

        return $intendedStatus;
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

        static::saved(function (Lead $lead): void {
            $lead->client?->syncStatusFromLeads();

            $previousClientId = $lead->getOriginal('client_id');

            if ($previousClientId && (int) $previousClientId !== (int) $lead->client_id) {
                Client::query()->find($previousClientId)?->syncStatusFromLeads();
            }
        });

        static::deleted(function (Lead $lead): void {
            Client::query()->find($lead->client_id)?->syncStatusFromLeads();
        });
    }
}
