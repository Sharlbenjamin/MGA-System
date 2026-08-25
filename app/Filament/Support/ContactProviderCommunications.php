<?php

namespace App\Filament\Support;

use App\Enums\CommunicationContextType;
use App\Models\Bill;
use App\Models\File;
use App\Models\Transaction;
use App\Services\Communications\CommunicationContextFactory;
use App\Services\Communications\CommunicationService;
use App\Support\Communications\CommunicationContext;
use App\Support\Communications\PhoneCandidate;
use Filament\Forms;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ContactProviderCommunications
{
    public static function canContact(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->can('contact providers');
    }

    /**
     * @param  Collection<int, File>  $records
     */
    public static function makeMissingDocumentsBulkAction(callable $resolveProvider, callable $resolveBranch): BulkAction
    {
        return BulkAction::make('contactProviderMissingDocuments')
            ->label('Request Missing Documents')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('success')
            ->visible(fn (): bool => self::canContact())
            ->deselectRecordsAfterCompletion()
            ->form(function (Collection $records) use ($resolveProvider, $resolveBranch) {
                $gapTypes = array_keys(CommunicationContextFactory::missingDocumentGapOptions());
                $context = app(CommunicationContextFactory::class)->forMissingDocuments(
                    $resolveProvider($records),
                    $records,
                    $resolveBranch($records),
                    Auth::user(),
                    $gapTypes,
                );

                return self::buildPreviewFormFields(
                    CommunicationContextType::MissingDocuments,
                    $context,
                    includeGapTypes: true,
                    onGapTypesChanged: function (array $state, Set $set) use ($records, $resolveProvider, $resolveBranch): void {
                        $updated = app(CommunicationContextFactory::class)->forMissingDocuments(
                            $resolveProvider($records),
                            $records,
                            $resolveBranch($records),
                            Auth::user(),
                            $state,
                        );
                        self::refreshMessageBody($set, CommunicationContextType::MissingDocuments, $updated);
                    },
                );
            })
            ->action(function (array $data, Collection $records) use ($resolveProvider, $resolveBranch): void {
                self::executeWhatsAppOpen(
                    $data,
                    fn () => app(CommunicationContextFactory::class)->forMissingDocuments(
                        $resolveProvider($records),
                        $records,
                        $resolveBranch($records),
                        Auth::user(),
                        $data['gap_types'] ?? [],
                    ),
                    CommunicationContextType::MissingDocuments,
                );
            });
    }

    /**
     * @param  Collection<int, File>  $records
     */
    public static function makeMissingBillsBulkAction(callable $resolveProvider, callable $resolveBranch): BulkAction
    {
        return BulkAction::make('contactProviderMissingBills')
            ->label('Request Missing Bills')
            ->icon('heroicon-o-document-text')
            ->color('warning')
            ->visible(fn (): bool => self::canContact())
            ->deselectRecordsAfterCompletion()
            ->form(function (Collection $records) use ($resolveProvider, $resolveBranch) {
                $context = app(CommunicationContextFactory::class)->forMissingBills(
                    $resolveProvider($records),
                    $records,
                    $resolveBranch($records),
                    Auth::user(),
                );

                return self::buildPreviewFormFields(CommunicationContextType::MissingBills, $context);
            })
            ->action(function (array $data, Collection $records) use ($resolveProvider, $resolveBranch): void {
                self::executeWhatsAppOpen(
                    $data,
                    fn () => app(CommunicationContextFactory::class)->forMissingBills(
                        $resolveProvider($records),
                        $records,
                        $resolveBranch($records),
                        Auth::user(),
                    ),
                    CommunicationContextType::MissingBills,
                );
            });
    }

    /**
     * @param  Collection<int, Bill>  $records
     */
    public static function makeOutstandingBillsBulkAction(callable $resolveProvider, callable $resolveBranch): BulkAction
    {
        return BulkAction::make('contactProviderOutstandingBills')
            ->label('Send Outstanding Bills Update')
            ->icon('heroicon-o-banknotes')
            ->color('info')
            ->visible(fn (): bool => self::canContact())
            ->deselectRecordsAfterCompletion()
            ->form(function (Collection $records) use ($resolveProvider, $resolveBranch) {
                $context = app(CommunicationContextFactory::class)->forOutstandingBills(
                    $resolveProvider($records),
                    $records,
                    $resolveBranch($records),
                    Auth::user(),
                );

                return self::buildPreviewFormFields(CommunicationContextType::OutstandingBillsAcknowledgement, $context);
            })
            ->action(function (array $data, Collection $records) use ($resolveProvider, $resolveBranch): void {
                self::executeWhatsAppOpen(
                    $data,
                    fn () => app(CommunicationContextFactory::class)->forOutstandingBills(
                        $resolveProvider($records),
                        $records,
                        $resolveBranch($records),
                        Auth::user(),
                    ),
                    CommunicationContextType::OutstandingBillsAcknowledgement,
                );
            });
    }

    /**
     * @param  Collection<int, Transaction>  $records
     */
    public static function makeTransactionBulkAction(callable $resolveProvider, callable $resolveBranch): BulkAction
    {
        return BulkAction::make('contactProviderTransactionDetails')
            ->label('Send Transaction Details')
            ->icon('heroicon-o-currency-euro')
            ->color('primary')
            ->visible(fn (): bool => self::canContact())
            ->deselectRecordsAfterCompletion()
            ->form(function (Collection $records) use ($resolveProvider, $resolveBranch) {
                $context = app(CommunicationContextFactory::class)->forTransactions(
                    $resolveProvider($records),
                    $records,
                    $resolveBranch($records),
                    Auth::user(),
                );

                return self::buildPreviewFormFields(CommunicationContextType::TransactionNotification, $context);
            })
            ->action(function (array $data, Collection $records) use ($resolveProvider, $resolveBranch): void {
                self::executeWhatsAppOpen(
                    $data,
                    fn () => app(CommunicationContextFactory::class)->forTransactions(
                        $resolveProvider($records),
                        $records,
                        $resolveBranch($records),
                        Auth::user(),
                    ),
                    CommunicationContextType::TransactionNotification,
                );
            });
    }

    /**
     * @param  callable(array, Set): void|null  $onGapTypesChanged
     * @return array<int, Forms\Components\Component>
     */
    public static function buildPreviewFormFields(
        CommunicationContextType $contextType,
        CommunicationContext $context,
        bool $includeGapTypes = false,
        ?callable $onGapTypesChanged = null,
    ): array {
        $service = app(CommunicationService::class);
        $template = $service->findTemplate($contextType);
        $recipients = $service->resolveWhatsAppRecipients($context);
        $defaultBody = $template ? $service->renderMessage($template, $context) : '';

        $schema = [];

        if ($includeGapTypes) {
            $schema[] = Forms\Components\CheckboxList::make('gap_types')
                ->label('Missing document types to include')
                ->options(CommunicationContextFactory::missingDocumentGapOptions())
                ->default(array_keys(CommunicationContextFactory::missingDocumentGapOptions()))
                ->columns(2)
                ->live()
                ->afterStateUpdated(function ($state, Set $set) use ($onGapTypesChanged): void {
                    if ($onGapTypesChanged) {
                        $onGapTypesChanged(is_array($state) ? $state : [], $set);
                    }
                });
        }

        $schema = array_merge($schema, [
            Forms\Components\Placeholder::make('channel')
                ->label('Channel')
                ->content('WhatsApp'),
            Forms\Components\Select::make('recipient_phone')
                ->label('Recipient')
                ->options($recipients->mapWithKeys(
                    fn (PhoneCandidate $candidate) => [$candidate->normalized => $candidate->label.' ('.$candidate->raw.')']
                )->all())
                ->default($recipients->first()?->normalized)
                ->required()
                ->disabled($recipients->count() <= 1)
                ->dehydrated(),
            Forms\Components\Placeholder::make('provider_display')
                ->label('Provider')
                ->content($context->provider?->name ?? 'N/A'),
            Forms\Components\Placeholder::make('branch_display')
                ->label('Provider Branch')
                ->content($context->providerBranch?->branch_name ?? 'N/A'),
            Forms\Components\Placeholder::make('template_display')
                ->label('Template')
                ->content($template?->name ?? $contextType->label()),
            Forms\Components\Textarea::make('message_body')
                ->label('Message')
                ->rows(14)
                ->default($defaultBody)
                ->required()
                ->columnSpanFull(),
        ]);

        return $schema;
    }

    public static function refreshMessageBody(
        Set $set,
        CommunicationContextType $contextType,
        CommunicationContext $context,
    ): void {
        $template = app(CommunicationService::class)->findTemplate($contextType);
        if ($template) {
            $set('message_body', app(CommunicationService::class)->renderMessage($template, $context));
        }
    }

    /**
     * @param  callable(): CommunicationContext  $contextBuilder
     */
    public static function executeWhatsAppOpen(array $data, callable $contextBuilder, CommunicationContextType $contextType): ?string
    {
        if (! self::canContact()) {
            Notification::make()->title('Not authorized')->danger()->send();

            return null;
        }

        $context = $contextBuilder();
        $service = app(CommunicationService::class);
        $template = $service->findTemplate($contextType);

        if (! $template) {
            Notification::make()
                ->title('Template not found')
                ->body('No active WhatsApp template exists for '.$contextType->label().'.')
                ->danger()
                ->send();

            return null;
        }

        $recipients = $service->resolveWhatsAppRecipients($context);
        $selectedPhone = $data['recipient_phone'] ?? $recipients->first()?->normalized;
        $recipient = $recipients->firstWhere('normalized', $selectedPhone) ?? $recipients->first();

        if (! $recipient) {
            Notification::make()
                ->title('No WhatsApp number')
                ->body('Could not resolve a WhatsApp phone number for this provider/branch.')
                ->danger()
                ->send();

            return null;
        }

        $body = trim((string) ($data['message_body'] ?? ''));
        if ($body === '') {
            Notification::make()->title('Message is empty')->danger()->send();

            return null;
        }

        $user = Auth::user();
        if (! $user) {
            Notification::make()->title('Not authenticated')->danger()->send();

            return null;
        }

        $log = $service->prepareLog($context, $template, $body, $user, $recipient);
        $url = $service->openWhatsApp($log, $recipient, $body);

        Notification::make()
            ->title('WhatsApp opened')
            ->body('Review the message in WhatsApp and press Send manually. Logged as opened, not sent.')
            ->success()
            ->actions([
                \Filament\Notifications\Actions\Action::make('open')
                    ->label('Open WhatsApp')
                    ->url($url)
                    ->openUrlInNewTab(),
                \Filament\Notifications\Actions\Action::make('markSent')
                    ->label('Mark as sent')
                    ->action(fn () => $service->markUserSent($log)),
            ])
            ->send();

        return $url;
    }
}
