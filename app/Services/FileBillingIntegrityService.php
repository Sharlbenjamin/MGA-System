<?php

namespace App\Services;

use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FileBillingIntegrityService
{
    public const AMOUNT_TOLERANCE = 0.01;

    public const COMMITTED_INVOICE_STATUSES = ['Posted', 'Not Sent', 'Sent', 'Unpaid', 'Partial', 'Paid'];

    public const SENT_INVOICE_STATUSES = ['Sent', 'Paid', 'Partial'];

    public const ISSUE_BILLS_EXCEED_INVOICE = 'bills_exceed_invoice';

    public const ISSUE_STALE_BILL_LINES = 'stale_bill_lines';

    public const ISSUE_BILL_AFTER_INVOICE_SENT = 'bill_after_invoice_sent';

    public static function issueTypeLabels(): array
    {
        return [
            self::ISSUE_BILLS_EXCEED_INVOICE => 'Bills total exceeds invoice total',
            self::ISSUE_STALE_BILL_LINES => 'Bill amounts differ from invoice bill lines',
            self::ISSUE_BILL_AFTER_INVOICE_SENT => 'Bill created or changed after invoice sent',
        ];
    }

    public static function issueTypeLabel(string $issueType): string
    {
        return self::issueTypeLabels()[$issueType] ?? ucfirst(str_replace('_', ' ', $issueType));
    }

    public static function billsTotalSubquerySql(): string
    {
        return '(SELECT COALESCE(SUM(bills.total_amount), 0) FROM bills WHERE bills.file_id = files.id)';
    }

    public static function invoicesTotalSubquerySql(): string
    {
        return '(SELECT COALESCE(SUM(invoices.total_amount), 0) FROM invoices WHERE invoices.file_id = files.id)';
    }

    public static function invoiceBillLinesSubquerySql(): string
    {
        $statuses = implode("','", self::COMMITTED_INVOICE_STATUSES);

        return "(SELECT COALESCE(SUM(invoice_items.amount), 0)
            FROM invoice_items
            INNER JOIN invoices ON invoices.id = invoice_items.invoice_id
            WHERE invoices.file_id = files.id
            AND invoices.status IN ('{$statuses}')
            AND invoice_items.item_type != '".InvoiceItem::TYPE_FILE_FEE."')";
    }

    public static function applyIssuesScope(Builder $query): Builder
    {
        return $query
            ->whereHas('invoices', fn (Builder $invoiceQuery): Builder => $invoiceQuery->whereIn('status', self::COMMITTED_INVOICE_STATUSES))
            ->where(function (Builder $issues): void {
                $issues
                    ->where(fn (Builder $q): Builder => self::applyBillsExceedInvoiceScope($q))
                    ->orWhere(fn (Builder $q): Builder => self::applyStaleBillLinesScope($q))
                    ->orWhere(fn (Builder $q): Builder => self::applyBillAfterInvoiceSentScope($q));
            });
    }

    public static function applyBillsExceedInvoiceScope(Builder $query): Builder
    {
        $billsTotal = self::billsTotalSubquerySql();
        $invoicesTotal = self::invoicesTotalSubquerySql();

        return $query->whereRaw("{$billsTotal} > {$invoicesTotal} + ?", [self::AMOUNT_TOLERANCE]);
    }

    public static function applyStaleBillLinesScope(Builder $query): Builder
    {
        $billsTotal = self::billsTotalSubquerySql();
        $invoiceBillLines = self::invoiceBillLinesSubquerySql();

        return $query->whereRaw("ABS({$billsTotal} - {$invoiceBillLines}) > ?", [self::AMOUNT_TOLERANCE]);
    }

    public static function applyBillAfterInvoiceSentScope(Builder $query): Builder
    {
        $sentStatuses = implode("','", self::SENT_INVOICE_STATUSES);

        return $query
            ->whereHas('invoices', fn (Builder $invoiceQuery): Builder => $invoiceQuery->whereIn('status', self::SENT_INVOICE_STATUSES))
            ->where(function (Builder $billActivity): void {
                $billActivity
                    ->whereHas('bills', function (Builder $billQuery): void {
                        $billQuery->whereRaw('bills.created_at > (
                            SELECT MIN(invoices.created_at)
                            FROM invoices
                            WHERE invoices.file_id = bills.file_id
                            AND invoices.status IN (\''.implode("','", self::SENT_INVOICE_STATUSES).'\')
                        )');
                    })
                    ->orWhereHas('bills', function (Builder $billQuery): void {
                        $billQuery->whereRaw('bills.updated_at > (
                            SELECT MAX(invoices.updated_at)
                            FROM invoices
                            WHERE invoices.file_id = bills.file_id
                            AND invoices.status IN (\''.implode("','", self::SENT_INVOICE_STATUSES).'\')
                        )');
                    });
            });
    }

    public static function applyIssueTypeScope(Builder $query, string $issueType): Builder
    {
        return match ($issueType) {
            self::ISSUE_BILLS_EXCEED_INVOICE => self::applyBillsExceedInvoiceScope($query),
            self::ISSUE_STALE_BILL_LINES => self::applyStaleBillLinesScope($query),
            self::ISSUE_BILL_AFTER_INVOICE_SENT => self::applyBillAfterInvoiceSentScope($query),
            default => $query,
        };
    }

    public static function billingIssueCount(): int
    {
        return self::issuesQuery()->count();
    }

    /**
     * @return array<string, int>
     */
    public static function issueTypeCounts(): array
    {
        $counts = [];

        foreach (array_keys(self::issueTypeLabels()) as $issueType) {
            $counts[$issueType] = self::applyIssueTypeScope(
                File::query()
                    ->whereHas('invoices', fn (Builder $invoiceQuery): Builder => $invoiceQuery->whereIn('status', self::COMMITTED_INVOICE_STATUSES)),
                $issueType,
            )->count();
        }

        return $counts;
    }

    public static function issuesQuery(): Builder
    {
        return self::applyIssuesScope(File::query());
    }

    /**
     * @return Collection<int, File>
     */
    public static function findBillingIssues(): Collection
    {
        $billsTotal = self::billsTotalSubquerySql();
        $invoicesTotal = self::invoicesTotalSubquerySql();
        $invoiceBillLines = self::invoiceBillLinesSubquerySql();

        return self::issuesQuery()
            ->select('files.*')
            ->selectRaw("{$billsTotal} as bills_total_sum")
            ->selectRaw("{$invoicesTotal} as invoices_total_sum")
            ->selectRaw("{$invoiceBillLines} as invoice_bill_lines_sum")
            ->selectRaw("({$billsTotal} - {$invoicesTotal}) as margin_delta")
            ->selectRaw("({$billsTotal} - {$invoiceBillLines}) as bill_lines_delta")
            ->orderBy('files.id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public static function describeIssues(File $file): array
    {
        $issues = [];

        if (! self::hasCommittedInvoice($file)) {
            return $issues;
        }

        $billsTotal = self::billsTotalFor($file);
        $invoicesTotal = self::invoicesTotalFor($file);
        $invoiceBillLines = self::invoiceBillLinesFor($file);

        if ($billsTotal > $invoicesTotal + self::AMOUNT_TOLERANCE) {
            $issues[] = self::ISSUE_BILLS_EXCEED_INVOICE;
        }

        if (abs($billsTotal - $invoiceBillLines) > self::AMOUNT_TOLERANCE) {
            $issues[] = self::ISSUE_STALE_BILL_LINES;
        }

        if (self::hasBillActivityAfterInvoiceSent($file)) {
            $issues[] = self::ISSUE_BILL_AFTER_INVOICE_SENT;
        }

        return array_values(array_unique($issues));
    }

    public static function describePrimaryIssue(File $file): ?string
    {
        $issues = self::describeIssues($file);

        return $issues[0] ?? null;
    }

    public static function hasCommittedInvoice(File $file): bool
    {
        if ($file->relationLoaded('invoices')) {
            return $file->invoices->contains(
                fn (Invoice $invoice): bool => in_array($invoice->status, self::COMMITTED_INVOICE_STATUSES, true),
            );
        }

        return $file->invoices()->whereIn('status', self::COMMITTED_INVOICE_STATUSES)->exists();
    }

    public static function hasSentInvoice(File $file): bool
    {
        if ($file->relationLoaded('invoices')) {
            return $file->invoices->contains(
                fn (Invoice $invoice): bool => in_array($invoice->status, self::SENT_INVOICE_STATUSES, true),
            );
        }

        return $file->invoices()->whereIn('status', self::SENT_INVOICE_STATUSES)->exists();
    }

    public static function billsTotalFor(File $file): float
    {
        if (isset($file->bills_total_sum)) {
            return round((float) $file->bills_total_sum, 2);
        }

        if ($file->relationLoaded('bills')) {
            return round((float) $file->bills->sum(fn ($bill) => (float) $bill->total_amount), 2);
        }

        return round((float) $file->bills()->sum('total_amount'), 2);
    }

    public static function invoicesTotalFor(File $file): float
    {
        if (isset($file->invoices_total_sum)) {
            return round((float) $file->invoices_total_sum, 2);
        }

        if ($file->relationLoaded('invoices')) {
            return round((float) $file->invoices->sum(fn ($invoice) => (float) $invoice->total_amount), 2);
        }

        return round((float) $file->invoices()->sum('total_amount'), 2);
    }

    public static function invoiceBillLinesFor(File $file): float
    {
        if (isset($file->invoice_bill_lines_sum)) {
            return round((float) $file->invoice_bill_lines_sum, 2);
        }

        return round((float) $file->invoices()
            ->whereIn('status', self::COMMITTED_INVOICE_STATUSES)
            ->withSum(['items as bill_lines_sum' => fn (Builder $query): Builder => $query->where('item_type', '!=', InvoiceItem::TYPE_FILE_FEE)], 'amount')
            ->get()
            ->sum('bill_lines_sum'), 2);
    }

    public static function marginDeltaFor(File $file): float
    {
        if (isset($file->margin_delta)) {
            return round((float) $file->margin_delta, 2);
        }

        return round(self::invoicesTotalFor($file) - self::billsTotalFor($file), 2);
    }

    public static function billLinesDeltaFor(File $file): float
    {
        if (isset($file->bill_lines_delta)) {
            return round((float) $file->bill_lines_delta, 2);
        }

        return round(self::billsTotalFor($file) - self::invoiceBillLinesFor($file), 2);
    }

    public static function hasBillActivityAfterInvoiceSent(File $file): bool
    {
        if (! self::hasSentInvoice($file)) {
            return false;
        }

        $sentInvoiceMilestone = $file->invoices()
            ->whereIn('status', self::SENT_INVOICE_STATUSES)
            ->min('created_at');

        if (blank($sentInvoiceMilestone)) {
            return false;
        }

        $latestSentInvoiceUpdate = $file->invoices()
            ->whereIn('status', self::SENT_INVOICE_STATUSES)
            ->max('updated_at');

        return $file->bills()
            ->where(function (Builder $query) use ($sentInvoiceMilestone, $latestSentInvoiceUpdate): void {
                $query->where('created_at', '>', $sentInvoiceMilestone)
                    ->orWhere('updated_at', '>', $latestSentInvoiceUpdate);
            })
            ->exists();
    }

    public static function warningForBillChange(File $file, string $action = 'update'): ?string
    {
        if (! self::hasCommittedInvoice($file)) {
            return null;
        }

        $ref = $file->mga_reference ?? ('File #'.$file->getKey());
        $billsTotal = number_format(self::billsTotalFor($file), 2);
        $invoicesTotal = number_format(self::invoicesTotalFor($file), 2);
        $invoiceBillLines = number_format(self::invoiceBillLinesFor($file), 2);

        if (self::hasSentInvoice($file)) {
            return match ($action) {
                'create' => "A bill is being added to {$ref} after the invoice was already sent. Current invoice total is €{$invoicesTotal}; invoice bill lines total €{$invoiceBillLines}.",
                default => "Bill {$action} on {$ref} after the invoice was sent. Live bills total €{$billsTotal}; invoice bill lines total €{$invoiceBillLines}. Regenerate or adjust the invoice if needed.",
            };
        }

        return match ($action) {
            'create' => "A bill is being added to {$ref} which already has a committed invoice (€{$invoicesTotal}). Invoice bill lines currently total €{$invoiceBillLines}.",
            default => "Bill {$action} on {$ref} with a committed invoice. Live bills total €{$billsTotal}; invoice bill lines total €{$invoiceBillLines}.",
        };
    }

    public static function shouldWarnForBillChange(File $file): bool
    {
        return self::hasCommittedInvoice($file);
    }
}
