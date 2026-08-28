<?php

namespace App\Filament\Resources\BillResource\Pages;

use App\Filament\Resources\BillResource;
use App\Filament\Resources\FileResource;
use App\Filament\Resources\TransactionResource;
use App\Filament\Support\FileBillingWarnings;
use App\Models\Transaction;
use App\Services\PaidBillDraftOutflowService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class EditBill extends EditRecord
{
    protected static string $resource = BillResource::class;

    #[On('refreshBillHeader')]
    public function refreshBillHeader(): void
    {
        $this->record->refresh();
        $this->record->unsetRelation('transactions');
        $this->fillForm();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['bank_account_id']) && ($data['bank_account_id'] === 0 || $data['bank_account_id'] === '0')) {
            $data['bank_account_id'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->record->unsetRelation('transactions');

        Notification::make()
            ->success()
            ->title('Bill updated successfully')
            ->send();

        FileBillingWarnings::notifyIfBillChangedOnFile($this->record->file, 'update');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_file')
                ->label('View File')
                ->url(FileResource::getUrl('view', ['record' => $this->record->file_id]))
                ->icon('heroicon-o-document-text'),
            $this->payBillAction(),
            ...$this->viewTransactionActions(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function payBillAction(): Actions\Action
    {
        $service = app(PaidBillDraftOutflowService::class);

        return Actions\Action::make('pay_bill')
            ->label('Pay bill')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (): bool => round((float) $this->record->total_amount, 2) > 0
                && $this->record->remainingBalance() > 0)
            ->modalHeading('Pay bill')
            ->modalDescription('Create a draft outflow and link it to this bill. Enter less than the bill total to mark it as partially paid.')
            ->modalSubmitActionLabel('Create payment')
            ->fillForm(fn (): array => [
                'payment_date' => $service->paymentDateDefault($this->record),
                'amount' => $service->paymentAmountDefault($this->record),
            ])
            ->form([
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Payment date')
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->inputMode('decimal')
                    ->step('0.01')
                    ->prefix('€')
                    ->required()
                    ->minValue(0.01)
                    ->maxValue(fn (): float => $this->record->remainingBalance())
                    ->helperText(fn (): string => 'Bill total: €'.number_format((float) $this->record->total_amount, 2)
                        .' · Remaining: €'.number_format($this->record->remainingBalance(), 2)),
            ])
            ->action(function (array $data) use ($service): void {
                try {
                    $service->pay(
                        $this->record,
                        (float) $data['amount'],
                        $data['payment_date'] ?? null,
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Payment could not be created')
                        ->body(collect($exception->errors())->flatten()->first() ?: 'Check the payment details.')
                        ->send();

                    return;
                }

                $this->record->refresh();
                $this->record->unsetRelation('transactions');
                $this->fillForm();
            });
    }

    /**
     * @return array<int, Actions\Action|Actions\ActionGroup>
     */
    protected function viewTransactionActions(): array
    {
        $transactions = $this->linkedTransactionsForView();

        if ($transactions->isEmpty()) {
            return [];
        }

        if ($transactions->count() === 1) {
            $transaction = $transactions->first();

            return [
                Actions\Action::make('view_transaction')
                    ->label('View Transaction')
                    ->icon('heroicon-o-rectangle-stack')
                    ->color('primary')
                    ->url(TransactionResource::getUrl('edit', ['record' => $transaction])),
            ];
        }

        return [
            Actions\ActionGroup::make(
                $transactions
                    ->map(fn (Transaction $transaction): Actions\Action => Actions\Action::make('view_transaction_'.$transaction->id)
                        ->label($transaction->name ?: ('Transaction #'.$transaction->id))
                        ->url(TransactionResource::getUrl('edit', ['record' => $transaction])))
                    ->all()
            )
                ->label('View Transaction')
                ->icon('heroicon-o-rectangle-stack')
                ->color('primary')
                ->button(),
        ];
    }

    /**
     * @return Collection<int, Transaction>
     */
    protected function linkedTransactionsForView(): Collection
    {
        $linked = $this->record->transactions()->orderByDesc('transactions.id')->get();

        if ($linked->isNotEmpty()) {
            return $linked;
        }

        if ($this->record->transaction_id) {
            $legacy = Transaction::query()->find($this->record->transaction_id);

            if ($legacy) {
                return collect([$legacy]);
            }
        }

        return collect();
    }
}
