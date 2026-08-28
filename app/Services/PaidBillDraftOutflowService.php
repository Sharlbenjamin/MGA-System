<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaidBillDraftOutflowService
{
    private static bool $syncing = false;

    public function syncFor(Bill $bill): ?Transaction
    {
        if (self::$syncing || ! $bill->exists) {
            return null;
        }

        self::$syncing = true;

        try {
            return DB::transaction(fn () => $this->syncForLocked($bill));
        } finally {
            self::$syncing = false;
        }
    }

    public function pay(Bill $bill, float $amount, mixed $paymentDate): Transaction
    {
        if (! $bill->exists) {
            throw ValidationException::withMessages([
                'amount' => 'Save the bill before recording a payment.',
            ]);
        }

        $bill->ensureProviderAndBranchRelationships();

        if (! $bill->provider_id) {
            throw ValidationException::withMessages([
                'amount' => 'This bill has no provider, so a payment cannot be created.',
            ]);
        }

        $remaining = $bill->remainingBalance();

        if ($remaining <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'This bill is already fully paid.',
            ]);
        }

        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter a payment amount greater than zero.',
            ]);
        }

        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'The payment cannot exceed the remaining €'.number_format($remaining, 2).'.',
            ]);
        }

        $parsedPaymentDate = $this->parseDate($paymentDate) ?? now();

        if (self::$syncing) {
            throw ValidationException::withMessages([
                'amount' => 'A payment is already being recorded for this bill.',
            ]);
        }

        self::$syncing = true;

        try {
            return DB::transaction(function () use ($bill, $amount, $parsedPaymentDate) {
                $bill->forceFill([
                    'payment_date' => $parsedPaymentDate->toDateString(),
                ])->saveQuietly();

                $bill->loadMissing([
                    'provider.country',
                    'branch.city',
                    'file.country',
                    'file.city',
                ]);

                return $this->createDraft($bill, $amount, $parsedPaymentDate);
            });
        } finally {
            self::$syncing = false;
        }
    }

    public function paymentAmountDefault(Bill $bill): float
    {
        $remaining = $bill->exists ? $bill->remainingBalance() : round((float) $bill->total_amount, 2);

        if ($remaining > 0) {
            return $remaining;
        }

        return round((float) $bill->total_amount, 2);
    }

    public function paymentDateDefault(Bill $bill): string
    {
        $billDate = $this->parseDate($bill->getAttributes()['bill_date'] ?? null);

        return ($billDate ?? now())->toDateString();
    }

    protected function syncForLocked(Bill $bill): ?Transaction
    {
        if ($bill->status !== 'Paid') {
            return null;
        }

        $existing = $this->findLinkedOutflow($bill);

        if ($existing) {
            return $existing;
        }

        $amount = $this->paymentAmountDefault($bill);

        if ($amount <= 0) {
            return null;
        }

        $bill->ensureProviderAndBranchRelationships();

        if (! $bill->provider_id) {
            return null;
        }

        $bill->loadMissing([
            'provider.country',
            'branch.city',
            'file.country',
            'file.city',
        ]);

        $paymentDate = $this->parseDate($bill->getAttributes()['payment_date'] ?? null)
            ?? $this->parseDate($bill->getAttributes()['bill_date'] ?? null)
            ?? now();

        if (! ($bill->getAttributes()['payment_date'] ?? null)) {
            $bill->forceFill([
                'payment_date' => $paymentDate->toDateString(),
            ])->saveQuietly();
        }

        return $this->createDraft($bill, $amount, $paymentDate);
    }

    public function findLinkedOutflow(Bill $bill): ?Transaction
    {
        $outflows = $this->linkedOutflows($bill);

        if ($outflows->isEmpty() && $bill->transaction_id) {
            $legacy = Transaction::query()->find($bill->transaction_id);
            if ($legacy && $legacy->type === 'Outflow') {
                $outflows = collect([$legacy]);
            }
        }

        return $outflows->first(fn (Transaction $transaction): bool => $transaction->status === 'Draft')
            ?? $outflows->first();
    }

    public function formatName(Bill $bill, CarbonInterface $createdAt, float $amount): string
    {
        $paymentDate = $this->paymentDate($bill, $createdAt);
        $location = $this->formatLocation($bill);

        $parts = array_values(array_filter([
            $createdAt->format('d/m/Y'),
            $bill->provider?->name,
            $location,
            $this->paymentStatusLabel($bill, $amount).' '.$paymentDate->format('d/m/Y'),
            '€'.number_format($amount, 2),
        ], fn ($part): bool => filled($part)));

        return mb_substr(implode(' · ', $parts), 0, 255);
    }

    public function formatNotes(Bill $bill, CarbonInterface $createdAt, float $amount): string
    {
        $paymentDate = $this->paymentDate($bill, $createdAt);
        [$country, $city] = $this->resolveCountryAndCity($bill);

        $lines = [
            'Auto-created draft outflow for paid bill '.$this->billLabel($bill),
            'Provider: '.($bill->provider?->name ?? '—'),
            'Country: '.($country ?: '—'),
            'City: '.($city ?: '—'),
            'Created: '.$createdAt->format('d/m/Y'),
            'Payment date: '.$paymentDate->format('d/m/Y'),
            'Amount: €'.number_format($amount, 2),
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array{0: ?string, 1: ?string} [country, city]
     */
    public function resolveCountryAndCity(Bill $bill): array
    {
        $country = $bill->file?->country?->name
            ?? $bill->provider?->country?->name
            ?? null;

        $city = $bill->file?->city?->name
            ?? $bill->branch?->city?->name
            ?? null;

        return [$country, $city];
    }

    public function formatLocation(Bill $bill): ?string
    {
        [$country, $city] = $this->resolveCountryAndCity($bill);

        if ($city && $country) {
            return $city.', '.$country;
        }

        return $city ?: $country;
    }

    public function buildAttributes(Bill $bill, float $amount, CarbonInterface $createdAt): array
    {
        return [
            'name' => $this->formatName($bill, $createdAt, $amount),
            'bank_account_id' => $this->defaultInternalBankAccountId(),
            'related_type' => 'Provider',
            'related_id' => $bill->provider_id,
            'amount' => $amount,
            'type' => 'Outflow',
            'date' => $this->paymentDate($bill, $createdAt)->toDateString(),
            'notes' => $this->formatNotes($bill, $createdAt, $amount),
            'status' => 'Draft',
            'documentation_category' => 'provider_single',
            'documentation_status' => 'incomplete',
            'created_by' => $this->currentUserId(),
            'updated_by' => $this->currentUserId(),
        ];
    }

    public function paymentStatusLabel(Bill $bill, float $amount): string
    {
        return round($amount, 2) < round((float) $bill->total_amount, 2) ? 'Partial' : 'Paid';
    }

    protected function createDraft(Bill $bill, float $amount, ?CarbonInterface $trxDate = null): Transaction
    {
        $createdAt = now();
        $trxDate ??= $this->paymentDate($bill, $createdAt);

        if (! ($bill->getAttributes()['payment_date'] ?? null)) {
            $bill->forceFill(['payment_date' => $trxDate->toDateString()])->saveQuietly();
        }

        $transaction = TransactionDocumentationService::withoutObserverSync(function () use ($bill, $amount, $createdAt) {
            $transaction = Transaction::query()->create($this->buildAttributes($bill, $amount, $createdAt));
            $this->syncBillLink($transaction, $bill, $amount);

            return $transaction;
        });

        if (! $bill->transaction_id) {
            $bill->forceFill(['transaction_id' => $transaction->id])->saveQuietly();
        }

        $bill->unsetRelation('transactions');
        $bill->recalculatePaidAmountFromTransactions();

        app(TransactionSettlementService::class)->syncAfterPivotChange($transaction->fresh());

        $this->notifyDraftCreated($transaction, $bill);

        return $transaction->fresh();
    }

    protected function syncBillLink(Transaction $transaction, Bill $bill, float $amount): void
    {
        if ($transaction->bills()->where('bills.id', $bill->id)->exists()) {
            $transaction->bills()->updateExistingPivot($bill->id, [
                'amount_paid' => $amount,
            ]);

            return;
        }

        $transaction->bills()->attach($bill->id, [
            'amount_paid' => $amount,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Transaction>
     */
    protected function linkedOutflows(Bill $bill): \Illuminate\Support\Collection
    {
        if ($bill->relationLoaded('transactions')) {
            return $bill->transactions
                ->filter(fn (Transaction $transaction): bool => $transaction->type === 'Outflow')
                ->values();
        }

        return $bill->transactions()->where('type', 'Outflow')->get();
    }

    protected function paymentDate(Bill $bill, CarbonInterface $fallback): CarbonInterface
    {
        return $this->parseDate($bill->getAttributes()['payment_date'] ?? null) ?? $fallback;
    }

    protected function parseDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy();
        }

        if (is_string($value) && $value !== '') {
            return \Carbon\Carbon::parse($value);
        }

        return null;
    }

    protected function billLabel(Bill $bill): string
    {
        if (filled($bill->name)) {
            return $bill->name;
        }

        return $bill->exists ? $bill->display_name : 'bill';
    }

    protected function currentUserId(): ?int
    {
        return Auth::id();
    }

    protected function defaultInternalBankAccountId(): ?int
    {
        $id = BankAccount::query()->where('type', 'Internal')->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }

    protected function notifyDraftCreated(Transaction $transaction, Bill $bill): void
    {
        try {
            Notification::make()
                ->title('Draft outflow created')
                ->body('A draft Outflow was linked to '.$this->billLabel($bill).'. Confirm it after the bank statement.')
                ->success()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('View transaction')
                        ->url(route('filament.admin.resources.transactions.edit', ['record' => $transaction])),
                ])
                ->send();
        } catch (Throwable) {
            // Filament notifications are unavailable outside the panel.
        }
    }
}
