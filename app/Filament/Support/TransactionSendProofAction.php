<?php

namespace App\Filament\Support;

use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class TransactionSendProofAction
{
    public static function make(): Action
    {
        return Action::make('send_proof')
            ->label('Send Proof')
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->visible(fn (?Transaction $record): bool => $record?->type === 'Outflow')
            ->modalHeading('Send Proof of Payment')
            ->modalDescription('Preview the email before sending it to the provider.')
            ->modalSubmitActionLabel('Send Email')
            ->form(function (?Transaction $record): array {
                if (! $record) {
                    return [];
                }

                return [
                    Forms\Components\View::make('proof_email_preview')
                        ->view('filament.forms.components.transaction-proof-email-preview')
                        ->viewData(static::previewData($record)),
                ];
            })
            ->action(function (?Transaction $record): void {
                if (! $record) {
                    return;
                }

                $transaction = $record->load([
                    'bills.file.patient',
                    'bills.provider',
                    'bills.branch.provider',
                ]);

                $provider = static::resolveProvider($transaction);
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
                            ->subject('Proof of Payment - '.($transaction->name ?? ('Transaction #'.$transaction->id)));

                        if (! empty($attachmentPath) && Storage::disk('public')->exists($attachmentPath)) {
                            $message->attachData(
                                Storage::disk('public')->get($attachmentPath),
                                basename($attachmentPath),
                            );
                        }
                    }
                );

                Notification::make()
                    ->success()
                    ->title('Proof email sent')
                    ->body('Proof of payment email was sent to '.$provider->email.'.')
                    ->send();
            });
    }

    /**
     * @return array{transaction: Transaction, bills: mixed, providerEmail: ?string, signature: mixed}
     */
    public static function previewData(Transaction $transaction): array
    {
        $transaction->loadMissing([
            'bills.file.patient',
            'bills.provider',
            'bills.branch.provider',
        ]);

        $provider = static::resolveProvider($transaction);

        return [
            'transaction' => $transaction,
            'bills' => $transaction->bills,
            'providerEmail' => $provider?->email,
            'signature' => Auth::user()?->signature,
        ];
    }

    public static function resolveProvider(Transaction $transaction): ?Provider
    {
        if ($transaction->related_type === 'Provider') {
            return Provider::find($transaction->related_id);
        }

        if ($transaction->related_type === 'Branch') {
            return ProviderBranch::with('provider')->find($transaction->related_id)?->provider;
        }

        $transaction->loadMissing(['bills.provider', 'bills.branch.provider']);

        if ($transaction->bills->isNotEmpty()) {
            return $transaction->bills->first()?->provider
                ?? $transaction->bills->first()?->branch?->provider;
        }

        return null;
    }
}
