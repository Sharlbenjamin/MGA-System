<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Mail\SendOutstandingBalance;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
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
use Illuminate\Support\HtmlString;

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
                ->modalHeading('Send Outstanding Balance')
                ->modalSubmitActionLabel('Send Email')
                ->modalWidth('7xl')
                ->form(function () {
                    $client = $this->record;
                    $invoices = $client->outstandingBalanceInvoicesQuery()
                        ->with(['patient', 'file'])
                        ->get();
                    $totalOutstanding = (float) $invoices->sum('total_amount');
                    $invoiceCount = $invoices->count();
                    $monthName = Carbon::now()->format('F');
                    $yearNumber = (int) Carbon::now()->format('Y');

                    return [
                        Forms\Components\Placeholder::make('balance_email_intro')
                            ->label('Email Preview')
                            ->content(new HtmlString(
                                '<div style="line-height:1.6;">' .
                                '<p><strong>To:</strong> ' . e($client->email ?? 'No financial email configured') . '</p>' .
                                '<p><strong>Subject:</strong> MGA x ' . e($client->company_name) . ' Outstanding for ' . e($monthName) . ' ' . e((string) $yearNumber) . '</p>' .
                                '<p>Dear team,</p>' .
                                '<p>Please note that the total outstanding is <strong>' . e(number_format($totalOutstanding, 2)) . ' EUR</strong> representing <strong>' . e((string) $invoiceCount) . '</strong> invoices.</p>' .
                                '</div>'
                            )),
                        Forms\Components\Placeholder::make('balance_table_preview')
                            ->label('Outstanding Invoices')
                            ->content(new HtmlString($this->buildOutstandingBalanceTableHtml($invoices))),
                        Forms\Components\TextInput::make('cc_emails')
                            ->label('CC Emails')
                            ->placeholder('finance@example.com, manager@example.com')
                            ->helperText('Separate multiple emails with commas.'),
                    ];
                })
                ->action(function (array $data) {
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
                    $ccEmails = collect(explode(',', (string) ($data['cc_emails'] ?? '')))
                        ->map(fn ($email) => trim($email))
                        ->filter()
                        ->unique()
                        ->values();

                    $invalidEmails = $ccEmails->filter(fn ($email) => !filter_var($email, FILTER_VALIDATE_EMAIL));

                    if ($invalidEmails->isNotEmpty()) {
                        Notification::make()
                            ->danger()
                            ->title('Invalid CC email(s)')
                            ->body('Please check: ' . $invalidEmails->implode(', '))
                            ->send();
                        return;
                    }

                    if ($user && $user->hasRole($financialRoles) && $user->smtp_username && $user->smtp_password) {
                        Config::set('mail.mailers.financial.username', $user->smtp_username);
                        Config::set('mail.mailers.financial.password', $user->smtp_password);
                    }

                    try {
                        $email = Mail::mailer($mailer)->to($recipientEmail);

                        if ($ccEmails->isNotEmpty()) {
                            $email->cc($ccEmails->all());
                        }

                        $email->send(new SendOutstandingBalance($client, $invoices, $monthName, $yearNumber));

                        Notification::make()
                            ->success()
                            ->title('Outstanding balance sent')
                            ->body("Email sent successfully to {$recipientEmail}" . ($ccEmails->isNotEmpty() ? ' with CC recipients.' : '.'))
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

    protected function buildOutstandingBalanceTableHtml($invoices): string
    {
        if ($invoices->isEmpty()) {
            return '<p style="color:#b45309;">No outstanding invoices found for this client.</p>';
        }

        $rows = $invoices->map(function ($invoice) {
            return '<tr>' .
                '<td style="padding:8px;border:1px solid #e5e7eb;">' . e($invoice->name) . '</td>' .
                '<td style="padding:8px;border:1px solid #e5e7eb;">' . e($invoice->patient?->name ?? '-') . '</td>' .
                '<td style="padding:8px;border:1px solid #e5e7eb;">' . e(optional($invoice->created_at)->format('d/m/Y')) . '</td>' .
                '<td style="padding:8px;border:1px solid #e5e7eb;">' . e(optional($invoice->due_date)->format('d/m/Y')) . '</td>' .
                '<td style="padding:8px;border:1px solid #e5e7eb;">' . e($invoice->file?->mga_reference ?? '-') . '</td>' .
                '<td style="padding:8px;border:1px solid #e5e7eb;">' . e($invoice->file?->client_reference ?? '-') . '</td>' .
                '<td style="padding:8px;border:1px solid #e5e7eb;text-align:right;">' . e(number_format((float) $invoice->total_amount, 2)) . ' EUR</td>' .
            '</tr>';
        })->implode('');

        return '<div style="max-height:320px;overflow:auto;border:1px solid #e5e7eb;border-radius:8px;">' .
            '<table style="width:100%;border-collapse:collapse;font-size:12px;">' .
            '<thead style="background:#f9fafb;">' .
            '<tr>' .
            '<th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Invoice</th>' .
            '<th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Patient</th>' .
            '<th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Date</th>' .
            '<th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Due Date</th>' .
            '<th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">MGA Ref</th>' .
            '<th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Client Ref</th>' .
            '<th style="padding:8px;border:1px solid #e5e7eb;text-align:right;">Amount</th>' .
            '</tr>' .
            '</thead>' .
            '<tbody>' . $rows . '</tbody>' .
            '</table>' .
            '</div>';
    }

}
