<?php

namespace App\Services;

use App\Models\File;
use App\Models\Gop;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

class FileWorkflowGapService
{
    public const GAP_GOP = 'gop';

    public const GAP_GOP_DOC = 'gop_doc';

    public const GAP_MR = 'mr';

    public const GAP_BILL = 'bill';

    public const GAP_ANY = 'any';

    public const INVOICE_GAP_INVOICE = 'invoice';

    public const INVOICE_GAP_INVOICE_DOC = 'invoice_doc';

    public const INVOICE_GAP_ANY = 'any';

    /**
     * @return array<string, string>
     */
    public static function assistedCheckpointOptions(): array
    {
        return [
            self::GAP_ANY => 'Any gap',
            self::GAP_GOP => 'Missing GOP',
            self::GAP_GOP_DOC => 'Missing GOP doc',
            self::GAP_MR => 'Missing MR',
            self::GAP_BILL => 'Missing bill',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function invoiceCheckpointOptions(): array
    {
        return [
            self::INVOICE_GAP_ANY => 'Any invoice gap',
            self::INVOICE_GAP_INVOICE => 'Missing invoice',
            self::INVOICE_GAP_INVOICE_DOC => 'Missing invoice doc',
        ];
    }

    public static function missingGop(File $file): bool
    {
        if ($file->relationLoaded('gops')) {
            return $file->gops->isEmpty();
        }

        return ! $file->gops()->exists();
    }

    public static function missingGopDoc(File $file): bool
    {
        if ($file->relationLoaded('gops')) {
            return $file->gops
                ->where('type', 'In')
                ->contains(fn (Gop $gop): bool => blank($gop->gop_google_drive_link));
        }

        return $file->gops()
            ->where('type', 'In')
            ->where(function (Builder $query): void {
                $query->whereNull('gop_google_drive_link')
                    ->orWhere('gop_google_drive_link', '');
            })
            ->exists();
    }

    public static function missingMr(File $file): bool
    {
        if ($file->relationLoaded('medicalReports')) {
            return $file->medicalReports->isEmpty();
        }

        return ! $file->medicalReports()->exists();
    }

    public static function missingBill(File $file): bool
    {
        if ($file->relationLoaded('bills')) {
            return $file->bills->isEmpty();
        }

        return ! $file->bills()->exists();
    }

    public static function hasAnyGap(File $file): bool
    {
        return self::missingGop($file)
            || self::missingGopDoc($file)
            || self::missingMr($file)
            || self::missingBill($file);
    }

    public static function scopeMissingGop(Builder $query): Builder
    {
        return $query->whereDoesntHave('gops');
    }

    public static function scopeMissingGopDoc(Builder $query): Builder
    {
        return $query->whereHas('gops', function (Builder $gopQuery): void {
            $gopQuery->where('type', 'In')
                ->where(function (Builder $linkQuery): void {
                    $linkQuery->whereNull('gop_google_drive_link')
                        ->orWhere('gop_google_drive_link', '');
                });
        });
    }

    public static function scopeMissingMr(Builder $query): Builder
    {
        return $query->whereDoesntHave('medicalReports');
    }

    public static function scopeMissingBill(Builder $query): Builder
    {
        return $query->whereDoesntHave('bills');
    }

    public static function scopeWithAnyGap(Builder $query): Builder
    {
        return $query->where(function (Builder $gapQuery): void {
            $gapQuery->where(fn (Builder $q) => self::scopeMissingGop($q))
                ->orWhere(fn (Builder $q) => self::scopeMissingGopDoc($q))
                ->orWhere(fn (Builder $q) => self::scopeMissingMr($q))
                ->orWhere(fn (Builder $q) => self::scopeMissingBill($q));
        });
    }

    public static function scopeWithGap(Builder $query, string $gapKey): Builder
    {
        return match ($gapKey) {
            self::GAP_GOP => self::scopeMissingGop($query),
            self::GAP_GOP_DOC => self::scopeMissingGopDoc($query),
            self::GAP_MR => self::scopeMissingMr($query),
            self::GAP_BILL => self::scopeMissingBill($query),
            default => self::scopeWithAnyGap($query),
        };
    }

    public static function scopeAssistedChecklistBase(Builder $query): Builder
    {
        return $query
            ->where('status', 'Assisted')
            ->tap(fn (Builder $scoped) => self::scopeWithAnyGap($scoped));
    }

    public static function missingInvoice(File $file): bool
    {
        if ($file->relationLoaded('invoices')) {
            return $file->invoices->isEmpty();
        }

        return ! $file->invoices()->exists();
    }

    public static function missingInvoiceDocument(File $file): bool
    {
        $pendingStatuses = ['Draft', 'Posted', 'Not Sent'];

        if ($file->relationLoaded('invoices')) {
            return $file->invoices->contains(
                fn (Invoice $invoice): bool => in_array($invoice->status, $pendingStatuses, true)
                    && blank($invoice->invoice_google_link)
            );
        }

        return $file->invoices()
            ->whereIn('status', $pendingStatuses)
            ->where(function (Builder $query): void {
                $query->whereNull('invoice_google_link')
                    ->orWhere('invoice_google_link', '');
            })
            ->exists();
    }

    public static function hasAnyInvoiceGap(File $file): bool
    {
        return self::missingInvoice($file) || self::missingInvoiceDocument($file);
    }

    public static function scopeMissingInvoice(Builder $query): Builder
    {
        return $query->whereDoesntHave('invoices');
    }

    public static function scopeMissingInvoiceDocument(Builder $query): Builder
    {
        return $query->whereHas('invoices', function (Builder $invoiceQuery): void {
            $invoiceQuery->whereIn('status', ['Draft', 'Posted', 'Not Sent'])
                ->where(function (Builder $linkQuery): void {
                    $linkQuery->whereNull('invoice_google_link')
                        ->orWhere('invoice_google_link', '');
                });
        });
    }

    public static function scopeWithAnyInvoiceGap(Builder $query): Builder
    {
        return $query->where(function (Builder $gapQuery): void {
            $gapQuery->where(fn (Builder $q) => self::scopeMissingInvoice($q))
                ->orWhere(fn (Builder $q) => self::scopeMissingInvoiceDocument($q));
        });
    }

    public static function scopeWithInvoiceGap(Builder $query, string $gapKey): Builder
    {
        return match ($gapKey) {
            self::INVOICE_GAP_INVOICE => self::scopeMissingInvoice($query),
            self::INVOICE_GAP_INVOICE_DOC => self::scopeMissingInvoiceDocument($query),
            default => self::scopeWithAnyInvoiceGap($query),
        };
    }

    public static function scopeInvoiceChecklistBase(Builder $query): Builder
    {
        return $query
            ->whereIn('status', ['Assisted', 'Waiting MR'])
            ->tap(fn (Builder $scoped) => self::scopeWithAnyInvoiceGap($scoped));
    }

    public static function firstGopInNeedingDocument(File $file): ?Gop
    {
        return $file->gops()
            ->where('type', 'In')
            ->where(function (Builder $query): void {
                $query->whereNull('gop_google_drive_link')
                    ->orWhere('gop_google_drive_link', '');
            })
            ->first();
    }

    public static function firstInvoiceNeedingDocument(File $file): ?Invoice
    {
        return $file->invoices()
            ->whereIn('status', ['Draft', 'Posted', 'Not Sent'])
            ->where(function (Builder $query): void {
                $query->whereNull('invoice_google_link')
                    ->orWhere('invoice_google_link', '');
            })
            ->orderBy('id')
            ->first();
    }
}
