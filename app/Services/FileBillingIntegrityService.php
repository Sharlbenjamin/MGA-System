<?php

namespace App\Services;

use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class FileBillingIntegrityService
{
    public const AMOUNT_TOLERANCE = 0.01;

    public const COMMITTED_INVOICE_STATUSES = ['Posted', 'Not Sent', 'Sent', 'Unpaid', 'Partial', 'Paid'];

    public const SENT_INVOICE_STATUSES = ['Sent', 'Paid', 'Partial'];

    public const ISSUE_BILLS_EXCEED_INVOICE = 'bills_exceed_invoice';

    public const ISSUE_STALE_BILL_LINES = 'stale_bill_lines';

    public const ISSUE_BILL_AFTER_INVOICE = 'bill_after_invoice';

    /** @deprecated Use ISSUE_BILL_AFTER_INVOICE */
    public const ISSUE_BILL_AFTER_INVOICE_SENT = self::ISSUE_BILL_AFTER_INVOICE;

    public static function issueTypeLabels(): array
    {
        return [
            self::ISSUE_BILLS_EXCEED_INVOICE => 'Bills total exceeds invoice total',
            self::ISSUE_STALE_BILL_LINES => 'Bill amounts differ from invoice bill lines',
            self::ISSUE_BILL_AFTER_INVOICE => 'Bill created after invoice',
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
                    ->where(fn (Builder $q): Builder => self::applyOpenBillsExceedInvoiceScope($q))
                    ->orWhere(fn (Builder $q): Builder => self::applyStaleBillLinesScope($q))
                    ->orWhere(fn (Builder $q): Builder => self::applyOpenBillAfterInvoiceScope($q));
            });
    }

    public static function applyBillsExceedInvoiceScope(Builder $query): Builder
    {
        $billsTotal = self::billsTotalSubquerySql();
        $invoicesTotal = self::invoicesTotalSubquerySql();

        return $query->whereRaw("{$billsTotal} > {$invoicesTotal} + ?", [self::AMOUNT_TOLERANCE]);
    }

    public static function applyOpenBillsExceedInvoiceScope(Builder $query): Builder
    {
        $billsTotal = self::billsTotalSubquerySql();
        $invoicesTotal = self::invoicesTotalSubquerySql();

        return self::applyBillsExceedInvoiceScope($query)
            ->where(function (Builder $open) use ($billsTotal, $invoicesTotal): void {
                $open
                    ->whereNull('accepted_bills_exceed_bills_total')
                    ->orWhereNull('accepted_bills_exceed_invoices_total')
                    ->orWhereRaw("ABS({$billsTotal} - files.accepted_bills_exceed_bills_total) > ?", [self::AMOUNT_TOLERANCE])
                    ->orWhereRaw("ABS({$invoicesTotal} - files.accepted_bills_exceed_invoices_total) > ?", [self::AMOUNT_TOLERANCE]);
            });
    }

    public static function applyStaleBillLinesScope(Builder $query): Builder
    {
        $billsTotal = self::billsTotalSubquerySql();
        $invoiceBillLines = self::invoiceBillLinesSubquerySql();

        return $query->whereRaw("ABS({$billsTotal} - {$invoiceBillLines}) > ?", [self::AMOUNT_TOLERANCE]);
    }

    public static function applyBillAfterInvoiceScope(Builder $query): Builder
    {
        $sentStatuses = implode("','", self::SENT_INVOICE_STATUSES);

        return $query
            ->whereHas('invoices', fn (Builder $invoiceQuery): Builder => $invoiceQuery->whereIn('status', self::SENT_INVOICE_STATUSES))
            ->whereHas('bills', function (Builder $billQuery) use ($sentStatuses): void {
                $billQuery->whereRaw("bills.created_at > (
                    SELECT MIN(invoices.created_at)
                    FROM invoices
                    WHERE invoices.file_id = bills.file_id
                    AND invoices.status IN ('{$sentStatuses}')
                )");
            });
    }

    public static function applyOpenBillAfterInvoiceScope(Builder $query): Builder
    {
        return self::applyBillAfterInvoiceScope($query)
            ->where(function (Builder $open): void {
                $open
                    ->whereNull('accepted_bill_after_at')
                    ->orWhereHas('bills', function (Builder $billQuery): void {
                        $billQuery->whereColumn('bills.created_at', '>', 'files.accepted_bill_after_at');
                    });
            });
    }

    public static function applyIssueTypeScope(Builder $query, string $issueType): Builder
    {
        return match ($issueType) {
            self::ISSUE_BILLS_EXCEED_INVOICE => self::applyOpenBillsExceedInvoiceScope($query),
            self::ISSUE_STALE_BILL_LINES => self::applyStaleBillLinesScope($query),
            self::ISSUE_BILL_AFTER_INVOICE, self::ISSUE_BILL_AFTER_INVOICE_SENT => self::applyOpenBillAfterInvoiceScope($query),
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
            ->selectRaw("({$invoicesTotal} - {$billsTotal}) as margin_delta")
            ->selectRaw("({$billsTotal} - {$invoiceBillLines}) as bill_lines_delta")
            ->orderBy('files.id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public static function describeIssues(File $file, bool $includeAccepted = false): array
    {
        $issues = [];

        if (! self::hasCommittedInvoice($file)) {
            return $issues;
        }

        if (self::billsExceedInvoice($file) && ($includeAccepted || ! self::isBillsExceedAccepted($file))) {
            $issues[] = self::ISSUE_BILLS_EXCEED_INVOICE;
        }

        if (self::hasStaleBillLines($file)) {
            $issues[] = self::ISSUE_STALE_BILL_LINES;
        }

        if (self::hasBillCreatedAfterInvoice($file) && ($includeAccepted || ! self::isBillAfterAccepted($file))) {
            $issues[] = self::ISSUE_BILL_AFTER_INVOICE;
        }

        return array_values(array_unique($issues));
    }

    public static function describePrimaryIssue(File $file): ?string
    {
        $issues = self::describeIssues($file);

        return $issues[0] ?? null;
    }

    public static function billsExceedInvoice(File $file): bool
    {
        return self::billsTotalFor($file) > self::invoicesTotalFor($file) + self::AMOUNT_TOLERANCE;
    }

    public static function hasStaleBillLines(File $file): bool
    {
        return abs(self::billsTotalFor($file) - self::invoiceBillLinesFor($file)) > self::AMOUNT_TOLERANCE;
    }

    public static function isBillsExceedAccepted(File $file): bool
    {
        if ($file->accepted_bills_exceed_bills_total === null || $file->accepted_bills_exceed_invoices_total === null) {
            return false;
        }

        $billsTotal = self::billsTotalFor($file);
        $invoicesTotal = self::invoicesTotalFor($file);

        return abs($billsTotal - (float) $file->accepted_bills_exceed_bills_total) <= self::AMOUNT_TOLERANCE
            && abs($invoicesTotal - (float) $file->accepted_bills_exceed_invoices_total) <= self::AMOUNT_TOLERANCE;
    }

    public static function isBillAfterAccepted(File $file): bool
    {
        if (blank($file->accepted_bill_after_at)) {
            return false;
        }

        if ($file->relationLoaded('bills')) {
            return ! $file->bills->contains(
                fn ($bill): bool => $bill->created_at > $file->accepted_bill_after_at,
            );
        }

        if (! $file->exists) {
            return true;
        }

        return ! $file->bills()
            ->where('created_at', '>', $file->accepted_bill_after_at)
            ->exists();
    }

    /**
     * Accept the currently open billing mismatches on a file.
     *
     * @param  list<string>|null  $issueTypes  Defaults to all currently open issues.
     */
    public static function acceptIssues(File $file, ?string $note = null, ?User $user = null, ?array $issueTypes = null): File
    {
        $openIssues = self::describeIssues($file);
        $toAccept = $issueTypes === null
            ? $openIssues
            : array_values(array_intersect($openIssues, $issueTypes));

        if ($toAccept === []) {
            return $file;
        }

        $payload = [
            'billing_mismatch_accepted_at' => now(),
            'billing_mismatch_accepted_by' => ($user ?? Auth::user())?->getKey(),
            'billing_mismatch_accepted_note' => filled($note) ? trim($note) : $file->billing_mismatch_accepted_note,
        ];

        if (in_array(self::ISSUE_BILLS_EXCEED_INVOICE, $toAccept, true)) {
            $payload['accepted_bills_exceed_bills_total'] = self::billsTotalFor($file);
            $payload['accepted_bills_exceed_invoices_total'] = self::invoicesTotalFor($file);
        }

        if (in_array(self::ISSUE_BILL_AFTER_INVOICE, $toAccept, true)
            || in_array(self::ISSUE_BILL_AFTER_INVOICE_SENT, $toAccept, true)) {
            $payload['accepted_bill_after_at'] = now();
        }

        $file->forceFill($payload)->save();

        return $file->refresh();
    }

    public static function clearAcceptance(File $file): File
    {
        $file->forceFill([
            'billing_mismatch_accepted_at' => null,
            'billing_mismatch_accepted_by' => null,
            'billing_mismatch_accepted_note' => null,
            'accepted_bills_exceed_bills_total' => null,
            'accepted_bills_exceed_invoices_total' => null,
            'accepted_bill_after_at' => null,
        ])->save();

        return $file->refresh();
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

    public static function hasBillCreatedAfterInvoice(File $file): bool
    {
        if (! self::hasSentInvoice($file)) {
            return false;
        }

        if ($file->relationLoaded('invoices') && $file->relationLoaded('bills')) {
            $sentInvoices = $file->invoices->filter(
                fn (Invoice $invoice): bool => in_array($invoice->status, self::SENT_INVOICE_STATUSES, true),
            );

            if ($sentInvoices->isEmpty()) {
                return false;
            }

            $sentInvoiceMilestone = $sentInvoices->min('created_at');

            return $file->bills->contains(
                fn ($bill): bool => $bill->created_at > $sentInvoiceMilestone,
            );
        }

        if (! $file->exists) {
            return false;
        }

        $sentInvoiceMilestone = $file->invoices()
            ->whereIn('status', self::SENT_INVOICE_STATUSES)
            ->min('created_at');

        if (blank($sentInvoiceMilestone)) {
            return false;
        }

        return $file->bills()
            ->where('created_at', '>', $sentInvoiceMilestone)
            ->exists();
    }

    /** @deprecated Use hasBillCreatedAfterInvoice() */
    public static function hasBillActivityAfterInvoiceSent(File $file): bool
    {
        return self::hasBillCreatedAfterInvoice($file);
    }

    public static function warningForBillChange(File $file, string $action = 'update'): ?string
    {
        if (! self::hasCommittedInvoice($file)) {
            return null;
        }

        $ref = $file->mga_reference ?? ('File #'.$file->getKey());
        $billsTotal = number_format(self::billsTotalFor($file), 2);
        $invoicesTotal = number_format(self::invoicesTotalFor($file), 2);

        if (self::hasSentInvoice($file)) {
            return match ($action) {
                'create' => "A bill is being added to {$ref} after the invoice was already sent. Current invoice total is €{$invoicesTotal}.",
                default => "Bill {$action} on {$ref} after the invoice was sent. Live bills total €{$billsTotal}; invoice total €{$invoicesTotal}.",
            };
        }

        return match ($action) {
            'create' => "A bill is being added to {$ref} which already has a committed invoice (€{$invoicesTotal}).",
            default => "Bill {$action} on {$ref} with a committed invoice. Live bills total €{$billsTotal}; invoice total €{$invoicesTotal}.",
        };
    }

    public static function shouldWarnForBillChange(File $file): bool
    {
        return self::hasCommittedInvoice($file);
    }
}
