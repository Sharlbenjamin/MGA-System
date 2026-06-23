<?php

namespace App\Filament\Support;

use App\Filament\Resources\TransactionResource;
use App\Models\Bill;
use App\Models\Transaction;
use App\Services\TransactionDocumentationStatsService;
use App\Services\TransactionSettlementService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class TransactionBillLinkForm
{
    /**
     * @param  array<int, int>  $excludeIds
     * @return array<int, Forms\Components\Component>
     */
    public static function billSelectField(
        Transaction $transaction,
        array $excludeIds = [],
    ): Forms\Components\Select {
        return Forms\Components\Select::make('bill_id')
            ->label('Bill')
            ->searchable()
            ->preload(false)
            ->required()
            ->live()
            ->getSearchResultsUsing(function (string $search) use ($transaction, $excludeIds): array {
                if (! in_array($transaction->related_type, ['Provider', 'Branch'], true) || ! $transaction->related_id) {
                    return [];
                }

                $options = TransactionResource::searchBillOptions(
                    $transaction->related_type,
                    (int) $transaction->related_id,
                    $transaction->id,
                    $search,
                );

                foreach ($excludeIds as $excludeId) {
                    unset($options[$excludeId]);
                }

                return $options;
            })
            ->getOptionLabelUsing(function ($value) use ($transaction): ?string {
                if (! $value) {
                    return null;
                }

                $bill = Bill::find((int) $value);

                return $bill ? TransactionResource::formatBillOptionLabel($bill) : null;
            })
            ->afterStateUpdated(function (?string $state, callable $set) use ($transaction): void {
                if (! $state) {
                    return;
                }

                $bill = Bill::find((int) $state);

                if ($bill) {
                    $set('amount_paid', self::defaultPaidAmountForBill($bill, $transaction));
                }
            });
    }

    public static function defaultPaidAmountForBill(Bill $bill, Transaction $transaction): float
    {
        return round(min($bill->remainingBalance(), (float) $bill->total_amount), 2);
    }

    public static function clampPaidAmountForBillOnTransaction(
        Bill $bill,
        Transaction $transaction,
        float $amount,
        ?float $currentPivotOnTransaction = null,
    ): float {
        $paid = round(max(0, $amount), 2);
        $currentPivot = $currentPivotOnTransaction ?? (float) ($transaction->bills()
            ->where('bills.id', $bill->id)
            ->first()?->pivot?->amount_paid ?? 0);
        $totalPaid = (float) DB::table('bill_transaction')
            ->where('bill_id', $bill->id)
            ->sum('amount_paid');
        $maxAllowed = round((float) $bill->total_amount - ($totalPaid - $currentPivot), 2);

        return min($paid, max(0, $maxAllowed));
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function attachFormSchema(Transaction $transaction, array $excludeIds = []): array
    {
        return [
            self::billSelectField($transaction, $excludeIds),
            Forms\Components\TextInput::make('amount_paid')
                ->label('Paid amount')
                ->numeric()
                ->inputMode('decimal')
                ->step('0.01')
                ->prefix('€')
                ->required()
                ->default(0),
            Forms\Components\Placeholder::make('bill_total_display')
                ->label('Bill total')
                ->content(function (Get $get): string {
                    $bill = Bill::find((int) $get('bill_id'));

                    if (! $bill) {
                        return '—';
                    }

                    return '€'.number_format((float) $bill->total_amount, 2);
                }),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function editPaidAmountSchema(Bill $bill): array
    {
        return [
            Forms\Components\Placeholder::make('bill_name')
                ->label('Bill')
                ->content($bill->name),
            Forms\Components\Placeholder::make('bill_total')
                ->label('Bill total')
                ->content('€'.number_format((float) $bill->total_amount, 2)),
            Forms\Components\TextInput::make('amount_paid')
                ->label('Paid amount on this transaction')
                ->numeric()
                ->inputMode('decimal')
                ->step('0.01')
                ->prefix('€')
                ->required(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function createRepeaterSchema(): array
    {
        return [
            Forms\Components\Select::make('bill_id')
                ->label('Bill')
                ->searchable()
                ->preload(false)
                ->required()
                ->live()
                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                ->getSearchResultsUsing(function (string $search, Get $get): array {
                    $relatedType = $get('../../related_type');
                    $relatedId = (int) ($get('../../related_id') ?? 0);

                    if (! in_array($relatedType, ['Provider', 'Branch'], true) || ! $relatedId) {
                        return [];
                    }

                    $selectedElsewhere = collect($get('../../bill_links') ?? [])
                        ->pluck('bill_id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    $currentId = (int) ($get('bill_id') ?? 0);
                    $exclude = array_values(array_filter(
                        $selectedElsewhere,
                        fn (int $id): bool => $id !== $currentId,
                    ));

                    $options = TransactionResource::searchBillOptions($relatedType, $relatedId, null, $search);

                    foreach ($exclude as $excludeId) {
                        unset($options[$excludeId]);
                    }

                    return $options;
                })
                ->getOptionLabelUsing(function ($value, Get $get): ?string {
                    if (! $value) {
                        return null;
                    }

                    $bill = Bill::find((int) $value);

                    return $bill ? TransactionResource::formatBillOptionLabel($bill) : null;
                })
                ->afterStateUpdated(function (?string $state, callable $set): void {
                    if (! $state) {
                        return;
                    }

                    $bill = Bill::find((int) $state);

                    if ($bill) {
                        $set('amount_paid', min($bill->remainingBalance(), (float) $bill->total_amount));
                    }
                }),
            Forms\Components\TextInput::make('amount_paid')
                ->label('Paid amount')
                ->numeric()
                ->inputMode('decimal')
                ->step('0.01')
                ->prefix('€')
                ->required()
                ->default(0),
            Forms\Components\Placeholder::make('bill_total_display')
                ->label('Bill total')
                ->content(function (Get $get): string {
                    $bill = Bill::find((int) $get('bill_id'));

                    if (! $bill) {
                        return '—';
                    }

                    return '€'.number_format((float) $bill->total_amount, 2);
                }),
        ];
    }

    public static function attachBill(Transaction $transaction, int $billId, float $amountPaid, bool $notify = true, bool $sync = true): void
    {
        $bill = Bill::find($billId);

        if (! $bill) {
            if ($notify) {
                Notification::make()->danger()->title('Bill not found')->send();
            }

            return;
        }

        $linked = $transaction->bills()->where('bills.id', $billId)->first();
        $existingPivot = $linked
            ? (float) ($linked->pivot->amount_paid ?? 0)
            : 0;

        $amountPaid = self::clampPaidAmountForBillOnTransaction(
            $bill,
            $transaction,
            $amountPaid,
            $existingPivot,
        );

        if ($linked) {
            $transaction->bills()->updateExistingPivot($billId, ['amount_paid' => $amountPaid]);
        } else {
            $transaction->bills()->attach($billId, ['amount_paid' => $amountPaid]);
        }

        $bill->recalculatePaidAmountFromTransactions();

        self::afterBillCountChange($transaction, $sync);

        if ($notify) {
            Notification::make()
                ->success()
                ->title('Bill added')
                ->body("{$bill->name} linked with €".number_format($amountPaid, 2).' paid.')
                ->send();
        }
    }

    public static function updatePaidAmount(Transaction $transaction, Bill $bill, float $amountPaid): void
    {
        $currentPivot = (float) ($transaction->bills()
            ->where('bills.id', $bill->id)
            ->first()?->pivot?->amount_paid ?? 0);

        $amountPaid = self::clampPaidAmountForBillOnTransaction(
            $bill,
            $transaction,
            $amountPaid,
            $currentPivot,
        );

        $transaction->bills()->updateExistingPivot($bill->id, ['amount_paid' => $amountPaid]);
        $bill->recalculatePaidAmountFromTransactions();
        app(TransactionSettlementService::class)->syncAfterPivotChange($transaction);

        Notification::make()
            ->success()
            ->title('Paid amount updated')
            ->send();
    }

    public static function detachBill(Transaction $transaction, Bill $bill): void
    {
        $transaction->bills()->detach($bill->id);
        $bill->recalculatePaidAmountFromTransactions();
        self::afterBillCountChange($transaction);

        Notification::make()
            ->success()
            ->title('Bill removed')
            ->body("{$bill->name} was unlinked from this transaction.")
            ->send();
    }

    /**
     * @param  array<int, array{bill_id?: int|string, amount_paid?: float|string}>  $links
     */
    public static function attachLinksFromCreate(Transaction $transaction, array $links): void
    {
        $count = 0;

        foreach ($links as $link) {
            $billId = (int) ($link['bill_id'] ?? 0);

            if (! $billId) {
                continue;
            }

            self::attachBill(
                $transaction,
                $billId,
                (float) ($link['amount_paid'] ?? 0),
                notify: false,
                sync: false,
            );
            $count++;
        }

        if ($count > 0) {
            app(TransactionDocumentationStatsService::class)
                ->applyProviderBillCategoryFromCount($transaction->fresh());

            app(TransactionSettlementService::class)->syncAfterPivotChange($transaction);

            Notification::make()
                ->title('Bills linked')
                ->body("Linked {$count} bill(s). Review paid amounts in the Bills tab.")
                ->info()
                ->send();
        }
    }

    protected static function afterBillCountChange(Transaction $transaction, bool $sync = true): void
    {
        $transaction = $transaction->fresh();

        app(TransactionDocumentationStatsService::class)
            ->applyProviderBillCategoryFromCount($transaction);

        if ($sync) {
            app(TransactionSettlementService::class)->syncAfterPivotChange($transaction->fresh());
        }
    }
}
