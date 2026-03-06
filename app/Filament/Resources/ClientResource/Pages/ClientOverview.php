<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Mail\SendOutstandingBalance;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ClientOverview extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ClientResource::class;

    public function getTitle(): string
    {
        return $this->record->company_name;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('company_name')->label('Company Name')->weight('bold')->color('success'),
                TextEntry::make('operation_email')->label('Operation Email'),
                // Operation Data
                TextEntry::make('filesCount')->label('Total Files')->color('info'),
                TextEntry::make('filesCancelledCount')->label('Total Cancelled Files')->color('info'),
                TextEntry::make('filesAssistedCount')->label('Total Assisted')->color('info'),
                //  Invoices Data
                TextEntry::make('invoicesTotalNumber')->label('Number of Invoices')->color('warning'),
                TextEntry::make('invoicesTotal')->label('Total Invoices')->color('warning')->money('eur'),
                TextEntry::make('invoicesTotalNumberPaid')->label('Number of Invoices Paid')->color('warning'),
                TextEntry::make('invoicesTotalPaid')->label('Total Invoices Paid')->color('warning')->money('eur'),
                TextEntry::make('invoicesTotalNumberOutstanding')->label('Number of Invoices Outstanding')->color('warning'),
                TextEntry::make('invoicesTotalOutstanding')->label('Total Invoices Outstanding')->color('warning')->money('eur'),
                // Transactions Data
                TextEntry::make('transactionsLastDate')->label('Last Transaction Date')->date('d-m-Y')->color('success'),
                TextEntry::make('transactionLastAmount')->label('Last Transaction Amount')->color('success')->money('eur'),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ClientResource::getEloquentQuery()
                    ->where('id', $this->record->id)
            )
            ->columns([
                TextColumn::make('company_name')
                    ->label('Company Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('invoicesTotalPaid')
                    ->label('Paid Amount')
                    ->money('eur')
                    ->sortable()
                    ->color('success'),
                TextColumn::make('invoicesTotalOutstanding')
                    ->label('Unpaid Amount')
                    ->money('eur')
                    ->sortable()
                    ->color('danger'),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendOutstandingBalance')
                ->label('Send Outstanding Balance')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Send Outstanding Balance')
                ->modalDescription('This will send the outstanding balance email to the client financial email.')
                ->action(function () {
                    $client = $this->record;
                    $invoices = $client->outstandingBalanceInvoicesQuery()
                        ->with(['patient', 'file'])
                        ->get();

                    if ($invoices->isEmpty()) {
                        Notification::make()
                            ->warning()
                            ->title('No outstanding invoices')
                            ->body('This client has no outstanding invoices to send.')
                            ->send();
                        return;
                    }

                    $recipientEmail = $client->email;

                    if (!$recipientEmail) {
                        Notification::make()
                            ->danger()
                            ->title('Missing financial email')
                            ->body('Please set the client financial email before sending.')
                            ->send();
                        return;
                    }

                    $monthName = Carbon::now()->format('F');
                    $yearNumber = (int) Carbon::now()->format('Y');
                    $mailer = 'financial';
                    $user = \App\Models\User::find(Auth::id());
                    $financialRoles = ['Financial Manager', 'Financial Supervisor', 'Financial Department'];

                    if ($user && $user->hasRole($financialRoles) && $user->smtp_username && $user->smtp_password) {
                        Config::set('mail.mailers.financial.username', $user->smtp_username);
                        Config::set('mail.mailers.financial.password', $user->smtp_password);
                    }

                    try {
                        Mail::mailer($mailer)
                            ->to($recipientEmail)
                            ->send(new SendOutstandingBalance($client, $invoices, $monthName, $yearNumber));

                        Notification::make()
                            ->success()
                            ->title('Outstanding balance sent')
                            ->body("Email sent successfully to {$recipientEmail}.")
                            ->send();
                    } catch (\Throwable $exception) {
                        Log::error('Failed to send outstanding balance email', [
                            'client_id' => $client->id,
                            'recipient_email' => $recipientEmail,
                            'error' => $exception->getMessage(),
                        ]);

                        Notification::make()
                            ->danger()
                            ->title('Failed to send outstanding balance')
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),
        ];
    }

}
