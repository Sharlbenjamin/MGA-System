<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Mail\SendOutstandingBalance;
use App\Models\Client;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    public string $viewMode = 'active';

    public function getTitle(): string
    {
        return $this->viewMode === 'active'
            ? 'Active Clients'
            : 'Potential Clients';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggleView')
                ->label(fn () => $this->viewMode === 'active' ? 'Potential Clients' : 'Active Clients')
                ->action(function () {
                    $this->viewMode = $this->viewMode === 'active' ? 'crm' : 'active';
                    $this->resetTable();
                })
                ->color('success'),
            Actions\Action::make('resetSentInvoicesToNotSent')
                ->label('Sent > 30 Days to Not Sent')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset Old Sent Invoices')
                ->modalDescription('This will change all invoices with status Sent and older than 30 days to Not Sent.')
                ->modalSubmitActionLabel('Reset Statuses')
                ->hidden(fn (): bool => $this->viewMode !== 'active')
                ->action(function () {
                    $cutoffDate = now()->subDays(30)->startOfDay();

                    $updatedCount = Invoice::query()
                        ->where('status', 'Sent')
                        ->where(function ($query) use ($cutoffDate) {
                            $query->whereDate('invoice_date', '<=', $cutoffDate)
                                ->orWhere(function ($fallbackQuery) use ($cutoffDate) {
                                    $fallbackQuery->whereNull('invoice_date')
                                        ->whereDate('created_at', '<=', $cutoffDate);
                                });
                        })
                        ->update(['status' => 'Not Sent']);

                    Notification::make()
                        ->success()
                        ->title('Old sent invoices updated')
                        ->body("{$updatedCount} invoice(s) were changed to Not Sent.")
                        ->send();

                    $this->resetTable();
                }),
            Actions\Action::make('clientsOutstandings')
                ->label('Clients Outstandings')
                ->icon('heroicon-o-document-currency-euro')
                ->color('info')
                ->hidden(fn (): bool => $this->viewMode !== 'active')
                ->modalHeading('Clients With Outstanding Invoices')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn () => view('filament.actions.clients-outstandings-modal', [
                    'clients' => $this->getOutstandingClientsSummary(),
                ])),
            Actions\CreateAction::make(),
        ];
    }

    protected function getOutstandingClientsSummary()
    {
        return Client::query()
            ->select('clients.id', 'clients.company_name')
            ->join('patients', 'patients.client_id', '=', 'clients.id')
            ->join('invoices', 'invoices.patient_id', '=', 'patients.id')
            ->whereRaw('LOWER(clients.status) = ?', ['active'])
            ->groupBy('clients.id', 'clients.company_name')
            ->selectRaw("SUM(CASE WHEN invoices.status = 'Unpaid' THEN COALESCE(invoices.total_amount, 0) ELSE 0 END) as total_outstanding")
            ->selectRaw("MAX(CASE WHEN invoices.status = 'Sent' THEN COALESCE(invoices.invoice_date, DATE(invoices.updated_at)) END) as last_outstanding_sent_date")
            ->selectRaw("SUM(CASE WHEN invoices.status = 'Unpaid' THEN 1 ELSE 0 END) as unpaid_invoices_count")
            ->havingRaw("SUM(CASE WHEN invoices.status = 'Unpaid' THEN COALESCE(invoices.total_amount, 0) ELSE 0 END) > 0")
            ->orderByDesc('total_outstanding')
            ->get();
    }

    public function sendOutstandingBalanceForClient(int $clientId): void
    {
        $client = Client::query()->find($clientId);

        if (!$client) {
            Notification::make()
                ->danger()
                ->title('Client not found')
                ->send();
            return;
        }

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

        $recipientEmail = $client->getOutstandingBalanceRecipientEmail();

        if (!$recipientEmail) {
            Notification::make()
                ->danger()
                ->title('Missing financial email')
                ->body("Please set the client financial email before sending for {$client->company_name}.")
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
                ->body("Email sent successfully to {$recipientEmail} for {$client->company_name}.")
                ->send();
        } catch (\Throwable $exception) {
            Log::error('Failed to send outstanding balance email', [
                'client_id' => $client->id,
                'recipient_email' => $recipientEmail,
                'error' => $exception->getMessage(),
            ]);

            try {
                Mail::mailer('smtp')
                    ->to($recipientEmail)
                    ->send(new SendOutstandingBalance($client, $invoices, $monthName, $yearNumber));

                Notification::make()
                    ->success()
                    ->title('Outstanding balance sent (fallback)')
                    ->body("Sent using SMTP fallback to {$recipientEmail} for {$client->company_name}.")
                    ->send();
            } catch (\Throwable $fallbackException) {
                Log::error('Fallback mailer also failed for outstanding balance email', [
                    'client_id' => $client->id,
                    'recipient_email' => $recipientEmail,
                    'financial_error' => $exception->getMessage(),
                    'smtp_error' => $fallbackException->getMessage(),
                ]);

                Notification::make()
                    ->danger()
                    ->title('Failed to send outstanding balance')
                    ->body('Financial mailer: ' . $exception->getMessage() . ' | SMTP: ' . $fallbackException->getMessage())
                    ->send();
            }
        }
    }

    public function table(Table $table): Table
    {
        $statusOptions = [
            'Searching' => 'Searching',
            'Interested' => 'Interested',
            'Sent' => 'Sent',
            'Rejected' => 'Rejected',
            'On Hold' => 'On Hold',
            'Broker' => 'Broker',
            'No Reply' => 'No Reply',
            'Active' => 'Active',
        ];

        // Potential Clients View (shows non-active clients)
        if ($this->viewMode === 'crm') {
            return $table
                ->query(
                    ClientResource::getEloquentQuery()
                        ->with('country')
                        ->whereRaw('LOWER(status) != ?', ['active'])
                )
                ->columns([
                    TextColumn::make('company_name')->searchable()->sortable()->label('Client Name')->sortable(),
                    TextColumn::make('country.name')->label('Country')->sortable()->searchable(),
                    TextColumn::make('type')->badge()->sortable()
                        ->color(fn (string $state): string => match ($state) {
                            'Assistance' => 'success',
                            'Insurance' => 'warning',
                            'Agency' => 'info',
                        }),
                    TextColumn::make('status')->badge()->sortable()
                        ->color(fn (string $state): string => match ($state) {
                            'Searching' => 'danger',
                            'Interested' => 'warning',
                            'Sent' => 'success',
                            'Rejected' => 'gray',
                            'On Hold' => 'gray',
                            'Broker' => 'success',
                            'No Reply' => 'danger',
                        }),
                    TextColumn::make('leadsCount')->label('Leads')->sortable(),
                    TextColumn::make('leadsLastContactDate')->label('Last Contact')->date('d-m-Y')->sortable(),
                ])->filters([
                    SelectFilter::make('status')
                        ->label('Status')
                        ->options($statusOptions),
                ])
                ->defaultSort('company_name', 'asc');
        }

        // Active Clients View (default - shows only active clients)
        return $table
            ->query(
                ClientResource::getEloquentQuery()
                    ->with('country')
                    ->addSelect([
                        'invoices_total_outstanding_sort' => Invoice::query()
                            ->selectRaw('COALESCE(SUM(COALESCE(invoices.total_amount, 0) - COALESCE(invoices.paid_amount, 0)), 0)')
                            ->join('patients', 'patients.id', '=', 'invoices.patient_id')
                            ->whereColumn('patients.client_id', 'clients.id'),
                    ])
                    ->whereRaw('LOWER(status) = ?', ['active'])
            )
            ->columns([
                TextColumn::make('company_name')
                    ->searchable()
                    ->sortable()
                    ->label('Company Name'),
                TextColumn::make('country.name')
                    ->label('Country')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Assistance' => 'success',
                        'Insurance' => 'warning',
                        'Agency' => 'info',
                    }),
                TextColumn::make('filesCount')
                    ->label('Total Files')
                    ->counts('files'),
                TextColumn::make('filesAssistedCount')
                    ->label('Assisted Files')
                    ->sortable(),
                TextColumn::make('invoicesTotalNumber')
                    ->label('Total Invoices')
                    ->sortable(),
                TextColumn::make('unsentInvoicesCount')
                    ->label('Unsent Invoices')
                    ->sortable(),
                TextColumn::make('invoicesTotal')
                    ->label('Total Amount')
                    ->money('eur')
                    ->sortable(),
                TextColumn::make('invoicesTotalPaid')
                    ->label('Paid Amount')
                    ->money('eur')
                    ->sortable(),
                TextColumn::make('invoicesTotalOutstanding')
                    ->label('Outstanding Amount')
                    ->money('eur')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('invoices_total_outstanding_sort', $direction);
                    }),
                TextColumn::make('transactionsLastDate')
                    ->label('Last Transaction')
                    ->date('d-m-Y')
                    ->sortable(),
            ])->actions([
                Tables\Actions\Action::make('Overview')
                ->url(fn (Client $record) => ClientResource::getUrl('overview', ['record' => $record]))->color('success'),
            ])
            ->defaultSort('invoices_total_outstanding_sort', 'desc');
    }
}
