<?php

namespace App\Filament\Support;

use App\Filament\Resources\TransactionResource\Pages\EditTransaction;
use App\Models\Transaction;
use App\Services\TransactionBillAmountSyncService;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class TransactionChangeAmountAction
{
    public static function makeHeaderAction(): \Filament\Actions\Action
    {
        /** @var \Filament\Actions\Action $action */
        $action = self::configure(\Filament\Actions\Action::make('changeTransactionAmount'));

        return $action;
    }

    public static function makeTableAction(): \Filament\Tables\Actions\Action
    {
        /** @var \Filament\Tables\Actions\Action $action */
        $action = self::configure(\Filament\Tables\Actions\Action::make('changeTransactionAmount'));

        return $action;
    }

    public static function configure(MountableAction $action): MountableAction
    {
        return $action
            ->label('Change amount')
            ->icon('heroicon-o-banknotes')
            ->color('primary')
            ->visible(fn (?Transaction $record): bool => self::canChange($record))
            ->modalHeading('Change payment amount')
            ->modalDescription(function (Transaction $record): string {
                $count = $record->relationLoaded('bills')
                    ? $record->bills->count()
                    : $record->bills()->count();

                if ($count > 1) {
                    return 'This payment covers '.$count.' bills. The difference between the current amount and the new amount is split evenly across those bills. Each bill total, and the amount this payment records on that bill, moves by its share.';
                }

                return 'The linked bill total and the amount this payment records on that bill will both be set to the new amount.';
            })
            ->modalSubmitActionLabel('Update amount')
            ->fillForm(fn (Transaction $record): array => [
                'new_total' => $record->amount,
            ])
            ->form(fn (Transaction $record): array => [
                Forms\Components\TextInput::make('new_total')
                    ->label('New amount')
                    ->numeric()
                    ->required()
                    ->prefix('€')
                    ->minValue(0.01)
                    ->live(debounce: 400)
                    ->helperText(
                        'Current amount: €'.number_format((float) $record->amount, 2)
                        .'. The change must be less than €'.number_format(TransactionBillAmountSyncService::MAX_AMOUNT_DIFFERENCE, 2).'.'
                    ),
                Forms\Components\Placeholder::make('bill_preview')
                    ->label('What will change')
                    ->content(function (Get $get) use ($record): HtmlString {
                        return new HtmlString(nl2br(e(self::preview($record, $get('new_total')))));
                    }),
            ])
            ->action(function (Transaction $record, array $data, Component $livewire): void {
                self::apply($record, (float) $data['new_total'], $livewire);
            });
    }

    public static function canChange(?Transaction $record): bool
    {
        if (! $record || $record->type !== 'Outflow') {
            return false;
        }

        return Gate::allows('update', $record)
            && app(TransactionBillAmountSyncService::class)->canSync($record);
    }

    public static function preview(Transaction $transaction, mixed $newTotal): string
    {
        $transaction->loadMissing('bills');
        $bills = $transaction->bills->sortBy('id')->values();

        if ($bills->isEmpty()) {
            return 'This payment has no linked bills.';
        }

        if ($newTotal === null || $newTotal === '') {
            return 'Enter the new amount to see how the linked bills will change.';
        }

        $service = app(TransactionBillAmountSyncService::class);
        $newTotal = round((float) $newTotal, 2);

        try {
            $service->validateNewTotal($transaction, $newTotal);
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->first() ?? 'Invalid amount.';
        }

        $totals = $service->resolveNewBillTotals($transaction, $newTotal);
        $lines = [
            'Payment: €'.number_format((float) $transaction->amount, 2).' → €'.number_format($newTotal, 2),
        ];

        foreach ($bills as $bill) {
            $newBillTotal = $totals[$bill->id] ?? $newTotal;
            $lines[] = sprintf(
                '%s: bill total €%s → €%s; paid on this payment €%s → €%s',
                $bill->name ?: ('Bill #'.$bill->id),
                number_format((float) $bill->total_amount, 2),
                number_format((float) $newBillTotal, 2),
                number_format((float) ($bill->pivot->amount_paid ?? 0), 2),
                number_format((float) $newBillTotal, 2),
            );
        }

        return implode("\n", $lines);
    }

    public static function apply(Transaction $record, float $newTotal, Component $livewire): void
    {
        $updated = app(TransactionBillAmountSyncService::class)->sync($record, $newTotal);

        if ($livewire instanceof EditTransaction) {
            $livewire->refreshRecordOnPage(full: true);
        } elseif (property_exists($livewire, 'record')) {
            $livewire->record = $updated;
        }

        $livewire->dispatch('refresh-transaction-documentation-stats');

        Notification::make()
            ->success()
            ->title('Amount updated')
            ->body('Payment and linked bill amounts now use €'.number_format((float) $updated->amount, 2).'.')
            ->send();
    }
}
