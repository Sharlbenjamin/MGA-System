<?php

namespace App\Filament\Resources\TaxesResource\Pages;

use App\Exports\TaxesModeExport;
use App\Filament\Resources\TaxesResource;
use App\Http\Controllers\TaxesExportController;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Invoice;
use App\Models\Bill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Filament\Widgets\TaxPeriodSelector;
use App\Filament\Widgets\TaxSummaryWidget;
use Livewire\Attributes\On;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ListTaxes extends ListRecords
{
    protected static string $resource = TaxesResource::class;

    public ?string $selectedYear = null;
    public ?string $selectedQuarter = null;

    public function mount(): void
    {
        parent::mount();
        $this->selectedYear = Carbon::now()->year;
        $this->selectedQuarter = (string) Carbon::now()->quarter;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TaxPeriodSelector::class,
            TaxSummaryWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export Data')
                ->icon('heroicon-o-arrow-down-tray')
                ->form([
                    Select::make('export_mode')
                        ->label('Export Option')
                        ->options([
                            'invoices_only' => 'Invoices Only',
                            'invoices_and_payments' => 'Invoices + Payments',
                        ])
                        ->default('invoices_and_payments')
                        ->required(),
                    TextInput::make('iva_percent')
                        ->label('IVA %')
                        ->numeric()
                        ->default(21)
                        ->required()
                        ->minValue(0)
                        ->maxValue(100),
                    Select::make('nif_source')
                        ->label('NIF Source')
                        ->options([
                            'country' => 'Country',
                            'niv_number' => 'NIV Number',
                        ])
                        ->default('country')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $year = $this->selectedYear ?? Carbon::now()->year;
                    $quarter = $this->selectedQuarter ?? '1';
                    
                    $url = route('taxes.export', [
                        'year' => $year,
                        'quarter' => $quarter,
                        'export_mode' => $data['export_mode'] ?? 'invoices_only',
                        'iva_percent' => $data['iva_percent'] ?? 21,
                        'nif_source' => $data['nif_source'] ?? 'country',
                    ]);
                    
                    return redirect($url);
                })
                ->extraAttributes([
                    'class' => 'bg-primary-600 hover:bg-primary-700',
                ]),
            Actions\Action::make('send_tax_email')
                ->label('Send Tax Email')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->form([
                    TextInput::make('to_email')
                        ->label('To Email')
                        ->email()
                        ->required(),
                    TextInput::make('cc_email')
                        ->label('CC Email (optional)')
                        ->email()
                        ->nullable(),
                    Select::make('export_mode')
                        ->label('Attachment Export Option')
                        ->options([
                            'invoices_only' => 'Invoices Only',
                            'invoices_and_payments' => 'Invoices + Payments',
                        ])
                        ->default('invoices_and_payments')
                        ->required(),
                    TextInput::make('iva_percent')
                        ->label('IVA %')
                        ->numeric()
                        ->default(21)
                        ->required()
                        ->minValue(0)
                        ->maxValue(100),
                    Select::make('nif_source')
                        ->label('NIF Source')
                        ->options([
                            'country' => 'Country',
                            'niv_number' => 'NIV Number',
                        ])
                        ->default('country')
                        ->required(),
                    TextInput::make('subject')
                        ->label('Email Subject')
                        ->default(function (): string {
                            $quarter = $this->selectedQuarter ?? (string) Carbon::now()->quarter;

                            return 'MEd Guard Assistance Tax documents for ' . $this->getQuarterCode($quarter);
                        })
                        ->required(),
                    Textarea::make('body')
                        ->label('Email Body')
                        ->rows(7)
                        ->default(function (): string {
                            $quarter = $this->selectedQuarter ?? (string) Carbon::now()->quarter;
                            $year = $this->selectedYear ?? Carbon::now()->year;

                            return sprintf(
                                'Mail please find below the list of the invoices of the %s of %s.',
                                $this->getQuarterDescription($quarter),
                                $year
                            );
                        })
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $year = (int) ($this->selectedYear ?? Carbon::now()->year);
                    $quarter = (string) ($this->selectedQuarter ?? '1');
                    $exportMode = (string) ($data['export_mode'] ?? 'invoices_only');
                    $ivaPercent = (float) ($data['iva_percent'] ?? 21);
                    $nifSource = (string) ($data['nif_source'] ?? 'country');

                    $payload = app(TaxesExportController::class)
                        ->buildExportPayload($year, $quarter, $exportMode, $ivaPercent, $nifSource);

                    $excelBinary = Excel::raw(
                        new TaxesModeExport($payload['rows'], $payload['headings']),
                        ExcelFormat::XLSX
                    );

                    $toEmail = strtolower(trim((string) $data['to_email']));
                    $ccEmail = trim((string) ($data['cc_email'] ?? ''));

                    $user = Auth::user();
                    if ($user) {
                        $freshUser = \App\Models\User::find($user->id);
                        $smtpUsername = $freshUser?->smtp_username ?? Config::get('mail.mailers.smtp.username');
                        $smtpPassword = $freshUser?->smtp_password ?? Config::get('mail.mailers.smtp.password');

                        if ($smtpUsername && $smtpPassword) {
                            Config::set('mail.mailers.smtp.username', $smtpUsername);
                            Config::set('mail.mailers.smtp.password', $smtpPassword);
                        }
                    }

                    $fromAddress = $user?->email ?: (string) config('mail.from.address');
                    $fromName = $user?->name ?: (string) config('mail.from.name');

                    Mail::raw((string) $data['body'], function ($message) use ($toEmail, $ccEmail, $data, $fromAddress, $fromName, $excelBinary, $payload): void {
                        $message->from($fromAddress, $fromName)
                            ->to($toEmail)
                            ->subject((string) $data['subject'])
                            ->attachData($excelBinary, $payload['filename'], [
                                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ]);

                        if ($ccEmail !== '') {
                            $message->cc(strtolower($ccEmail));
                        }
                    });

                    Notification::make()
                        ->success()
                        ->title('Tax email sent')
                        ->body('Email sent successfully from your account with the Excel attachment.')
                        ->send();
                }),
            Actions\Action::make('export_zip')
                ->label('Export Zip')
                ->icon('heroicon-o-archive-box')
                ->color('success')
                ->action(function () {
                    $year = $this->selectedYear ?? Carbon::now()->year;
                    $quarter = $this->selectedQuarter ?? '1';
                    
                    // Call the export method directly instead of redirecting
                    $controller = new \App\Http\Controllers\TaxesExportController();
                    $request = new \Illuminate\Http\Request();
                    $request->merge([
                        'year' => $year,
                        'quarter' => $quarter,
                    ]);
                    
                    return $controller->exportZip($request);
                })
                ->extraAttributes([
                    'class' => 'bg-green-600 hover:bg-green-700',
                ]),
        ];
    }

    public function downloadExport()
    {
        $year = $this->selectedYear ?? Carbon::now()->year;
        $quarter = $this->selectedQuarter ?? '1';

        // Calculate date range based on quarter
        if ($quarter !== 'full') {
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth = $startMonth + 2;
            $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
            $endDate = Carbon::create($year, $endMonth, 1)->endOfMonth();
        } else {
            $startDate = Carbon::create($year, 1, 1)->startOfYear();
            $endDate = Carbon::create($year, 12, 31)->endOfYear();
        }

        $paidInvoiceFileIds = Invoice::query()
            ->where('status', 'Paid')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->whereNotNull('file_id')
            ->distinct()
            ->pluck('file_id');

        // Calculate totals
        $invoiceTotal = Invoice::query()
            ->where('status', 'Paid')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('total_amount');
        $billTotal = Bill::query()
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->whereIn('file_id', $paidInvoiceFileIds)
            ->sum('total_amount');
        $expenseTotal = DB::table('transactions')
            ->join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
            ->where('transactions.type', 'Expense')
            ->where('bank_accounts.type', 'Internal')
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->sum('transactions.amount');

        // Get filtered data
        $data = $this->getTableQuery()->get();

        // Prepare export data
        $exportData = [];
        foreach ($data as $record) {
            $exportData[] = [
                'Document Number' => $record->document_number,
                'Type' => ucfirst($record->type),
                'Amount' => number_format($record->total_amount, 2) . ' €',
                'Status' => $record->status,
                'Document Date' => $record->document_date,
                'Notes' => $record->transaction_notes ?? '',
                'Created Date' => $record->created_at,
                'Due Date' => $record->due_date,
            ];
        }

        // Add summary rows
        $exportData[] = []; // Empty row
        $exportData[] = ['SUMMARY', '', '', '', '', '', '', ''];
        $exportData[] = ['Invoice Total', '', number_format($invoiceTotal, 2) . ' €', '', '', '', '', ''];
        $exportData[] = ['Bill Total', '', number_format($billTotal, 2) . ' €', '', '', '', '', ''];
        $exportData[] = ['Expense Total', '', number_format($expenseTotal, 2) . ' €', '', '', '', '', ''];
        $exportData[] = ['Net Total', '', number_format($invoiceTotal - $billTotal - $expenseTotal, 2) . ' €', '', '', '', '', ''];

        // Generate filename
        $filename = "taxes_report_{$year}_Q{$quarter}_" . now()->format('Y-m-d_H-i-s') . '.csv';
        
        // Create CSV content
        $csv = $this->createCsvContent($exportData);
        
        // Return the data for JavaScript download
        return [
            'csv' => $csv,
            'filename' => $filename
        ];
    }

    private function createCsvContent($data)
    {
        $csv = '';
        
        // Add headers
        if (!empty($data)) {
            $csv .= implode(',', array_keys($data[0])) . "\n";
        }
        
        // Add data rows
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($row as $value) {
                // Escape commas and quotes in CSV
                $value = str_replace('"', '""', $value);
                $csvRow[] = '"' . $value . '"';
            }
            $csv .= implode(',', $csvRow) . "\n";
        }
        
        return $csv;
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('Document Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'invoice',
                        'success' => 'bill',
                        'danger' => 'expense',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                
                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('EUR')
                    ->sortable(),
                
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Paid' => 'success',
                        'Sent' => 'warning',
                        'Draft' => 'gray',
                        'Unpaid' => 'danger',
                        'Partial' => 'info',
                        'Expense' => 'danger',
                        default => 'gray',
                    }),
                
                TextColumn::make('document_date')
                    ->label('Document Date')
                    ->date()
                    ->sortable(),
                
                TextColumn::make('transaction_notes')
                    ->label('Notes')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(50),
                
                TextColumn::make('google_drive_link')
                    ->label('View Document')
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        if ($state && ($record->type === 'bill' || $record->type === 'invoice')) {
                            return '<a href="' . $state . '" target="_blank" class="text-primary-600 hover:text-primary-500 underline">View Document</a>';
                        }
                        return '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('created_at')
                    ->label('Created Date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Document Type')
                    ->options([
                        'invoice' => 'Invoice',
                        'bill' => 'Bill',
                        'expense' => 'Expense',
                    ]),
                
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Paid' => 'Paid',
                        'Sent' => 'Sent',
                        'Draft' => 'Draft',
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                        'Expense' => 'Expense',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => match ($record->type) {
                        'invoice' => route('filament.admin.resources.invoices.edit', $record->id),
                        'bill' => route('filament.admin.resources.bills.edit', $record->id),
                        'expense' => route('filament.admin.resources.transactions.edit', $record->id),
                        default => '#',
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->label('Export Selected'),
                ]),
            ])
            ->defaultSort('document_date', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    protected function getTableQuery(): Builder
    {
        $year = $this->selectedYear ?? Carbon::now()->year;
        $quarter = $this->selectedQuarter ?? '1';

        // Calculate date range based on quarter
        if ($quarter !== 'full') {
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth = $startMonth + 2;
            $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
            $endDate = Carbon::create($year, $endMonth, 1)->endOfMonth();
        } else {
            $startDate = Carbon::create($year, 1, 1)->startOfYear();
            $endDate = Carbon::create($year, 12, 31)->endOfYear();
        }

        $paidInvoiceFileIds = Invoice::query()
            ->where('status', 'Paid')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->whereNotNull('file_id')
            ->select('file_id')
            ->distinct();

        // Query only paid invoices for the selected period
        $invoices = Invoice::query()
            ->where('status', 'Paid')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->select([
                'id',
                'name as document_number',
                'total_amount',
                'created_at',
                'invoice_date as document_date',
                'status',
                'due_date',
                DB::raw("'invoice' as type"),
                DB::raw("name as invoice_number"),
                DB::raw("NULL as bill_number"),
                DB::raw("NULL as transaction_notes"),
                'invoice_google_link as google_drive_link'
            ]);

        // Query bills for the selected period, but only for cases with paid invoices
        $bills = Bill::query()
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->whereIn('file_id', $paidInvoiceFileIds)
            ->select([
                'id',
                'name as document_number',
                'total_amount',
                'created_at',
                'bill_date as document_date',
                'status',
                'due_date',
                DB::raw("'bill' as type"),
                DB::raw("NULL as invoice_number"),
                DB::raw("name as bill_number"),
                DB::raw("NULL as transaction_notes"),
                'bill_google_link as google_drive_link'
            ]);

        // Query only expense transactions for the selected period.
        // Outflow can duplicate bills already listed above.
        $expenses = DB::table('transactions')
            ->join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
            ->where('transactions.type', 'Expense')
            ->where('bank_accounts.type', 'Internal') // Med Guard is the internal account
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->select([
                'transactions.id',
                'transactions.name as document_number',
                'transactions.amount as total_amount',
                'transactions.created_at',
                'transactions.date as document_date',
                DB::raw("transactions.type as status"),
                DB::raw("NULL as due_date"),
                DB::raw("'expense' as type"),
                DB::raw("NULL as invoice_number"),
                DB::raw("NULL as bill_number"),
                'transactions.notes as transaction_notes',
                DB::raw("NULL as google_drive_link")
            ]);

        return $invoices->union($bills)->union($expenses);
    }

    protected function getTableFiltersFormSchema(): array
    {
        return [];
    }

    protected function getTableFiltersFormColumns(): int
    {
        return 3;
    }

    protected function getTableFiltersFormWidth(): string
    {
        return '4xl';
    }

    #[On('tax-period-changed')]
    public function onTaxPeriodChanged($data)
    {
        $this->selectedYear = $data['year'];
        $this->selectedQuarter = $data['quarter'];
        $this->resetTable();
    }

    private function getQuarterCode(string $quarter): string
    {
        return $quarter === 'full' ? 'Full Year' : 'Q' . $quarter;
    }

    private function getQuarterDescription(string $quarter): string
    {
        return match ($quarter) {
            '1' => 'first quarter',
            '2' => 'second quarter',
            '3' => 'third quarter',
            '4' => 'fourth quarter',
            default => 'full year',
        };
    }
} 