<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Mail\GopMailable;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Traits\LogsActivity;

class Gop extends Model
{
    use HasFactory, LogsActivity;

    public const IN_STATUS_DRAFT = 'Draft';

    public const IN_STATUS_OFFERED = 'Offered';

    public const IN_STATUS_ACCEPTED = 'Accepted';

    public const IN_STATUS_REJECTED = 'Rejected';

    public const OUT_STATUS_NOT_SENT = 'Not Sent';

    public const OUT_STATUS_SENT = 'Sent';

    public const OUT_STATUS_UPDATED = 'Updated';

    public const OUT_STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'file_id',
        'provider_branch_id',
        'service_type_id',
        'service_type_other',
        'type',
        'amount',
        'offered_cost',
        'file_fee',
        'notes',
        'offer_sections',
        'status',
        'date',
        'gop_google_drive_link',
        'document_path',
    ];

    protected $casts = [
        'id' => 'integer',
        'file_id' => 'integer',
        'provider_branch_id' => 'integer',
        'service_type_id' => 'integer',
        'amount' => 'float',
        'offered_cost' => 'float',
        'file_fee' => 'float',
        'offer_sections' => 'array',
        'date' => 'date',
        'status' => 'string',
    ];

    protected static function booted(): void
    {
        static::saving(function (Gop $gop): void {
            if ($gop->type !== 'In') {
                return;
            }

            if ($gop->offered_cost !== null || $gop->file_fee !== null) {
                $gop->amount = round(
                    (float) ($gop->offered_cost ?? 0) + (float) ($gop->file_fee ?? 0),
                    2,
                );
            }
        });
    }

    public static function inStatusOptions(): array
    {
        return [
            self::IN_STATUS_DRAFT => 'Draft',
            self::IN_STATUS_OFFERED => 'Offered',
            self::IN_STATUS_ACCEPTED => 'Accepted',
            self::IN_STATUS_REJECTED => 'Rejected',
        ];
    }

    public static function outStatusOptions(): array
    {
        return [
            self::OUT_STATUS_NOT_SENT => 'Not Sent',
            self::OUT_STATUS_SENT => 'Sent',
            self::OUT_STATUS_UPDATED => 'Updated',
            self::OUT_STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function isIn(): bool
    {
        return $this->type === 'In';
    }

    public function isOut(): bool
    {
        return $this->type === 'Out';
    }

    public function isAcceptedOffer(): bool
    {
        return $this->isIn() && $this->status === self::IN_STATUS_ACCEPTED;
    }

    public function getEffectiveOfferedCostAttribute(): float
    {
        if ($this->offered_cost !== null) {
            return (float) $this->offered_cost;
        }

        return (float) $this->amount;
    }

    public function getEffectiveFileFeeAttribute(): float
    {
        return (float) ($this->file_fee ?? 0);
    }

    public function getActivityReference(): ?string
    {
        $ref = $this->file?->mga_reference ?? 'File #' . $this->file_id;
        return "GOP {$this->type} #{$this->id} ({$ref})";
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function providerBranch(): BelongsTo
    {
        return $this->belongsTo(ProviderBranch::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GopItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getEffectiveServiceTypeNameAttribute(): string
    {
        if (filled($this->service_type_other)) {
            return trim($this->service_type_other);
        }

        return trim((string) ($this->serviceType?->name ?? ''));
    }

    public function sendGopToBranch()
    {

        if($this->type == 'In'){
            return;
        }
        $branch = $this->provider_branch_id
            ? $this->providerBranch
            : $this->file->providerBranch;
        if (!$branch) {
            Notification::make()->title('GOP Notification')->body('This file doesn\'t have a branch')->danger()->send();
            return false;
        }
        $gopContact = $branch->contacts()->where('title', 'like', '%GOP%')->first();

        if (!$gopContact?->email) {
            Notification::make()->title('GOP Notification')->body('This branch doesn\'t have a GOP Contact')->danger()->send();
            return false;
        }

        try {
            Mail::to($gopContact->email)->send(new GopMailable($this));
            $this->status = self::OUT_STATUS_SENT;
            $this->save();
            Notification::make()->title('GOP Notification')->body('GOP sent to branch')->success()->send();
            return true;

        } catch (\Exception $e) {
            Notification::make()->title('GOP Notification')->body('Failed to send GOP: ' . $e->getMessage())->danger()->send();
            return false;
        }
    }

    /**
     * Check if the GOP has a local document
     */
    public function hasLocalDocument(): bool
    {
        return !empty($this->document_path);
    }

    /**
     * Generate a signed URL for the GOP document
     * 
     * @param int $expirationMinutes Expiration time in minutes (default: 60)
     * @return string|null
     */
    public function getDocumentSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (!$this->hasLocalDocument()) {
            return null;
        }

        return URL::temporarySignedRoute('docs.serve', now()->addMinutes($expirationMinutes), [
            'type' => 'gop',
            'id' => $this->id
        ]);
    }

    /**
     * Generate a signed URL for document metadata
     * 
     * @param int $expirationMinutes Expiration time in minutes (default: 60)
     * @return string|null
     */
    public function getDocumentMetadataSignedUrl(int $expirationMinutes = 60): ?string
    {
        if (!$this->hasLocalDocument()) {
            return null;
        }

        return URL::temporarySignedRoute('docs.metadata', now()->addMinutes($expirationMinutes), [
            'type' => 'gop',
            'id' => $this->id
        ]);
    }
}
