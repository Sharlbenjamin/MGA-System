<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Filament\Notifications\Notification;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Log;
use App\Traits\HasContacts;
use App\Traits\NotifiableEntity;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Support\ClientEmailRecipients;
use App\Traits\LogsActivity;

class Client extends Model
{
    use HasFactory, HasContacts, NotifiableEntity, LogsActivity;

    public const FILE_FEE_STRATEGY_TIER = 'tier';

    public const FILE_FEE_STRATEGY_MULTIPLIER = 'multiplier';

    public const INVOICE_TEMPLATE_ITEMIZED = 'itemized';

    public const INVOICE_TEMPLATE_COMBINED = 'combined';

    public const STATUS_GROUP_ACTIVE = 'active';

    public const STATUS_GROUP_INACTIVE = 'inactive';

    public const STATUS_GROUP_POTENTIAL = 'potential';

    /** @var list<string> */
    public const ACTIVE_STATUSES = ['Active'];

    /** @var list<string> */
    public const INACTIVE_STATUSES = ['Rejected', 'On Hold', 'Closed', 'Black list'];

    /** @var list<string> */
    public const POTENTIAL_STATUSES = ['Searching', 'Interested', 'Sent', 'Broker', 'No Reply'];

    protected $fillable = [
        'company_name',
        'type',
        'status',
        'initials',
        'country_id',
        'niv_number',
        'number_requests',
        'gop_contact_id',
        'operation_contact_id',
        'financial_contact_id',
        'phone',
        'linkedin_url',
        'email',
        'operation_email',
        'invoice_cc_emails',
        'invoice_file_fee_strategy',
        'invoice_template',
        'address',
        'signed_contract_draft',
        'comment',
    ];

    protected $attributes = [
        'invoice_file_fee_strategy' => self::FILE_FEE_STRATEGY_TIER,
        'invoice_template' => self::INVOICE_TEMPLATE_ITEMIZED,
    ];

    protected $casts = [
        'id' => 'integer',
        'country_id' => 'integer',
        'invoice_cc_emails' => 'array',
    ];

    public function usesCombinedInvoiceTemplate(): bool
    {
        return $this->invoice_template === self::INVOICE_TEMPLATE_COMBINED;
    }

    public function usesMultiplierFileFeeStrategy(): bool
    {
        return $this->invoice_file_fee_strategy === self::FILE_FEE_STRATEGY_MULTIPLIER;
    }

    public function allowsBillAttachmentWithInvoice(): bool
    {
        return ! $this->usesCombinedInvoiceTemplate();
    }

    public function getNameAttribute()
    {
        return $this->company_name;
    }

    public function linkedInUrl(): ?string
    {
        $value = trim((string) ($this->linkedin_url ?: ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $value) === 1) {
            return $value;
        }

        return 'https://'.$value;
    }

    /**
     * Reference used in activity log.
     */
    public function getActivityReference(): ?string
    {
        return $this->company_name ?? ('Client #' . $this->getKey());
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest();
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        $options = [];

        foreach ([...self::POTENTIAL_STATUSES, ...self::ACTIVE_STATUSES, ...self::INACTIVE_STATUSES] as $status) {
            $options[$status] = $status;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptionsForGroup(string $group): array
    {
        $options = [];

        foreach (self::statusesForGroup($group) as $status) {
            $options[$status] = $status;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function statusesForGroup(string $group): array
    {
        return match ($group) {
            self::STATUS_GROUP_ACTIVE => self::ACTIVE_STATUSES,
            self::STATUS_GROUP_INACTIVE => self::INACTIVE_STATUSES,
            self::STATUS_GROUP_POTENTIAL => self::POTENTIAL_STATUSES,
            default => [],
        };
    }

    public static function normalizeStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return match ($normalized) {
            'black', 'blacklist', 'black list' => 'black list',
            default => $normalized,
        };
    }

    public static function statusGroup(?string $status): ?string
    {
        $normalized = self::normalizeStatus($status);

        if ($normalized === '') {
            return null;
        }

        foreach (self::ACTIVE_STATUSES as $activeStatus) {
            if (self::normalizeStatus($activeStatus) === $normalized) {
                return self::STATUS_GROUP_ACTIVE;
            }
        }

        foreach (self::INACTIVE_STATUSES as $inactiveStatus) {
            if (self::normalizeStatus($inactiveStatus) === $normalized) {
                return self::STATUS_GROUP_INACTIVE;
            }
        }

        foreach (self::POTENTIAL_STATUSES as $potentialStatus) {
            if (self::normalizeStatus($potentialStatus) === $normalized) {
                return self::STATUS_GROUP_POTENTIAL;
            }
        }

        return null;
    }

    /**
     * Lowercased status values used in SQL filters (includes Black-list aliases).
     *
     * @return list<string>
     */
    public static function normalizedStatusesForGroup(string $group): array
    {
        $normalized = array_map(
            fn (string $status): string => self::normalizeStatus($status),
            self::statusesForGroup($group),
        );

        if ($group === self::STATUS_GROUP_INACTIVE) {
            $normalized = array_merge($normalized, ['blacklist', 'black']);
        }

        return array_values(array_unique($normalized));
    }

    public function scopeInStatusGroup(Builder $query, string $group): Builder
    {
        $normalized = self::normalizedStatusesForGroup($group);

        if ($normalized === []) {
            return $query->whereRaw('1 = 0');
        }

        $placeholders = implode(', ', array_fill(0, count($normalized), '?'));

        return $query->whereRaw('LOWER(clients.status) IN ('.$placeholders.')', $normalized);
    }

    public function isInStatusGroup(string $group): bool
    {
        return self::statusGroup($this->status) === $group;
    }

    /**
     * Sent when any lead is past the Error / No Reply steps.
     * Searching when the client has no leads, or only Error and No Reply leads.
     * Only potential-pipeline clients are auto-synced; Active/Inactive stay fixed.
     */
    public function syncStatusFromLeads(): void
    {
        if (! $this->isInStatusGroup(self::STATUS_GROUP_POTENTIAL)) {
            return;
        }

        $hasOutreachStep = $this->leads()
            ->whereRaw('LOWER(leads.status) NOT IN (?, ?)', ['error', 'no reply'])
            ->exists();

        $status = $hasOutreachStep ? 'Sent' : 'Searching';

        if ($this->status === $status) {
            return;
        }

        $this->update(['status' => $status]);
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

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function files(): HasManyThrough
    {
        return $this->hasManyThrough(
            File::class,
            Patient::class,
            'client_id',
            'patient_id',
            'id',
            'id'
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'related_id')->where('related_type', 'Client');
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function notifyClient($type, $data, $message = null)
    {
        $reason = $this->detectNotificationReason($data);
        $this->sendNotification($reason, $type, $data, 'Client', $message);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'client_id', 'id')->where('type', 'Client');
    }

    public function sendWhatsAppMessage($type, $file)
    {
        try {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $from = 'whatsapp:' . config('services.twilio.whatsapp_from');
            $client = new TwilioClient($sid, $token);

            $contact = $this->primaryContact('Invoice');
            $recipient = $contact ? 'whatsapp:' . $contact->phone_number : null;

            if (!$recipient) {
                Log::error("Twilio WhatsApp Error: No recipient phone number available.");
                return false;
            }

            $message = $client->messages->create(
                $recipient,
                [
                    "from" => $from,
                    "body" => "Your invoice notification message here."
                ]
            );

            Log::info("Twilio WhatsApp Success: Message SID - " . $message->sid);
            return $message->sid;
        } catch (\Exception $e) {
            Log::error("Twilio WhatsApp Error: " . $e->getMessage());
            return false;
        }
    }





    // Over View Calculations

    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, Patient::class);
    }

    public function outstandingBalanceInvoicesQuery()
    {
        return $this->invoices()->where('status', 'Unpaid');
    }

    public function getOutstandingBalanceRecipientEmail(): ?string
    {
        $financialContact = $this->financialContact;

        if ($financialContact) {
            if ($financialContact->preferred_contact === 'Second Email' && !empty($financialContact->second_email)) {
                return $financialContact->second_email;
            }

            if (!empty($financialContact->email)) {
                return $financialContact->email;
            }
        }

        return $this->email;
    }

    public function getInvoiceRecipientEmail(): ?string
    {
        return $this->getOutstandingBalanceRecipientEmail();
    }

    /**
     * @return array<int, string>
     */
    public function getInvoiceCcEmails(): array
    {
        return ClientEmailRecipients::normalizeList(is_array($this->invoice_cc_emails) ? $this->invoice_cc_emails : []);
    }

    /**
     * @param  array<int, string|null>  $emails
     * @return array<int, string>
     */
    public function getValidatedEmailList(array $emails, ?string $excludeTo = null): array
    {
        $excludeTo ??= $this->getInvoiceRecipientEmail();

        return ClientEmailRecipients::validateList(
            array_merge($this->getInvoiceCcEmails(), $emails),
            $excludeTo,
        )['valid'];
    }



    public function getFilesCountAttribute()
    {
        return $this->files()->count();
    }

    public function getFilesCancelledCountAttribute()
    {
        return $this->files()->where('status', 'Cancelled')->count();
    }

    public function getFilesAssistedCountAttribute()
    {
        return $this->files()->where('status', 'Assisted')->count();
    }

    public function getInvoicesTotalNumberAttribute()
    {
        return $this->invoices()->count();
    }

    public function getUnsentInvoicesCountAttribute()
    {
        return $this->invoices()->whereIn('status', ['Draft', 'Posted'])->count();
    }

    public function getInvoicesTotalAttribute()
    {
        return $this->invoices()->sum('total_amount');
    }

    public function getInvoicesTotalPaidAttribute()
    {
        // Calculate paid amount from actual transaction relationships
        return $this->invoices()
            ->with('transactions')
            ->get()
            ->sum(function ($invoice) {
                return $invoice->transactions->sum(function ($transaction) {
                    return $transaction->pivot->amount_paid ?? 0;
                });
            });
    }

    public function getInvoicesTotalNumberOutstandingAttribute()
    {
        return $this->invoices()->where('status', '!=', 'Paid')->count();
    }

    public function getInvoicesTotalNumberPaidAttribute()
    {
        return $this->invoices()->where('status', 'Paid')->count();
    }

    public function getInvoicesTotalOutstandingAttribute()
    {
        // Calculate outstanding amount as total minus actual paid amount
        $totalAmount = $this->invoices_total;
        $paidAmount = $this->invoices_total_paid;
        return $totalAmount - $paidAmount;
    }

    public function getTransactionsLastDateAttribute()
    {
        return $this->transactions()->latest()->first()?->date;
    }


    public function getTransactionLastAmountAttribute()
    {
        return $this->transactions()->latest()->first()?->amount;
    }


    public function getLeadsCountAttribute()
    {
        return $this->leads()->count();
    }

    public function getLeadsLastContactDateAttribute()
    {
        return $this->leads()->latest()->first()?->last_contact_date;
    }

    /**
     * Main lead = furthest pipeline step (latest progress); ties use last contact then id.
     */
    public function mainLead(): ?Lead
    {
        if ($this->relationLoaded('leads')) {
            return $this->leads
                ->sort(function (Lead $a, Lead $b): int {
                    $rank = Lead::pipelineStepIndex($b->status) <=> Lead::pipelineStepIndex($a->status);

                    if ($rank !== 0) {
                        return $rank;
                    }

                    $aDate = optional($a->last_contact_date)?->timestamp ?? 0;
                    $bDate = optional($b->last_contact_date)?->timestamp ?? 0;

                    if ($aDate !== $bDate) {
                        return $bDate <=> $aDate;
                    }

                    return ($b->id ?? 0) <=> ($a->id ?? 0);
                })
                ->first();
        }

        return $this->leads()
            ->get()
            ->sort(function (Lead $a, Lead $b): int {
                $rank = Lead::pipelineStepIndex($b->status) <=> Lead::pipelineStepIndex($a->status);

                if ($rank !== 0) {
                    return $rank;
                }

                $aDate = optional($a->last_contact_date)?->timestamp ?? 0;
                $bDate = optional($b->last_contact_date)?->timestamp ?? 0;

                if ($aDate !== $bDate) {
                    return $bDate <=> $aDate;
                }

                return ($b->id ?? 0) <=> ($a->id ?? 0);
            })
            ->first();
    }

    public function leadPipelineProgressPercent(): int
    {
        return $this->mainLead()?->pipelineProgressPercent() ?? 0;
    }

    public function mainLeadStatus(): ?string
    {
        return $this->mainLead()?->status;
    }
}
