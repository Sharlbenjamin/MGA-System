<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Action::make('send_proof')
                ->label('Send Proof')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => $this->record?->type === 'Outflow')
                ->modalHeading('Send Proof of Payment')
                ->modalDescription('Preview the email before sending it to the provider.')
                ->modalSubmitActionLabel('Send Email')
                ->form([
                    Forms\Components\View::make('proof_email_preview')
                        ->view('filament.forms.components.transaction-proof-email-preview')
                        ->viewData($this->getProofEmailPreviewData()),
                ])
                ->action(function (): void {
                    $transaction = $this->record->load([
                        'bills.file.patient',
                        'bills.provider',
                        'bills.branch.provider',
                    ]);

                    $provider = $this->resolveProvider($transaction);
                    if (! $provider || empty($provider->email)) {
                        Notification::make()
                            ->danger()
                            ->title('Provider email missing')
                            ->body('No provider email found for this transaction.')
                            ->send();

                        return;
                    }

                    $attachmentPath = $transaction->isUploadedFile()
                        ? $transaction->attachment_path
                        : null;

                    Mail::send(
                        'emails.financial.send-transaction-proof',
                        [
                            'transaction' => $transaction,
                            'bills' => $transaction->bills,
                            'signature' => Auth::user()?->signature,
                        ],
                        function ($message) use ($provider, $transaction, $attachmentPath): void {
                            $message->to($provider->email)
                                ->subject('Proof of Payment - ' . ($transaction->name ?? ('Transaction #' . $transaction->id)));

                            if (! empty($attachmentPath)) {
                                $message->attachFromStorageDisk(
                                    'public',
                                    $attachmentPath,
                                    basename($attachmentPath)
                                );
                            }
                        }
                    );

                    Notification::make()
                        ->success()
                        ->title('Proof email sent')
                        ->body('Proof of payment email was sent to ' . $provider->email . '.')
                        ->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        // Load the record with all necessary relationships
        $record = $this->record->load([
            'invoices.file.patient.client',
            'bills.file.patient.client',
            'bills.provider',
            'bills.branch',
            'bankAccount'
        ]);
        
        // Calculate widgets data - using proper relationship loading
        $invoices = $record->invoices()->with(['file.bills'])->get();
        
        // Debug: Check what we have
        $invoicesWithFiles = $invoices->filter(function($invoice) {
            return $invoice->file !== null;
        });
        
        $filesCount = $invoicesWithFiles->pluck('file_id')->unique()->count();
        
        // Calculate total cost by iterating through invoices manually
        $totalCost = 0;
        foreach ($invoicesWithFiles as $invoice) {
            if ($invoice->file && $invoice->file->bills) {
                $totalCost += $invoice->file->bills->sum('total_amount');
            }
        }
        
        $totalInvoices = $invoices->sum('total_amount');
        $totalProfit = $totalInvoices - $totalCost;
        
        return [
            'record' => $record,
            'filesCount' => $filesCount,
            'totalCost' => $totalCost,
            'totalProfit' => $totalProfit,
            'totalInvoices' => $totalInvoices,
        ];
    }

    public function getView(): string
    {
        return 'filament.resources.transaction-resource.pages.view-transaction';
    }

    protected function getProofEmailPreviewData(): array
    {
        $transaction = $this->record->loadMissing([
            'bills.file.patient',
            'bills.provider',
            'bills.branch.provider',
        ]);

        $provider = $this->resolveProvider($transaction);

        return [
            'transaction' => $transaction,
            'bills' => $transaction->bills,
            'providerEmail' => $provider?->email,
            'signature' => Auth::user()?->signature,
        ];
    }

    protected function resolveProvider(Transaction $transaction): ?Provider
    {
        if ($transaction->related_type === 'Provider') {
            return Provider::find($transaction->related_id);
        }

        if ($transaction->related_type === 'Branch') {
            return ProviderBranch::with('provider')->find($transaction->related_id)?->provider;
        }

        if ($transaction->bills->isNotEmpty()) {
            return $transaction->bills->first()?->provider
                ?? $transaction->bills->first()?->branch?->provider;
        }

        return null;
    }
} 