<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers\ItemsRelationManager;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Services\UploadInvoiceToGoogleDrive;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-currency-euro';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationGroup = 'Ops';
    protected static ?string $recordTitleAttribute = 'name';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\Select::make('file_id')
                            ->relationship('file', 'mga_reference', fn (Builder $query, Get $get) => $query->where('patient_id', $get('patient_id')))
                            ->required()
                            ->searchable()->preload()
                            ->default(fn () => request()->get('file_id'))
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->mga_reference)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $file = \App\Models\File::find($state);
                                    if ($file) {
                                        $set('patient_id', $file->patient_id);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('patient_id')
                            ->relationship('patient', 'name')
                            ->required()
                            ->searchable()->preload()
                            ->live()
                            ->default(fn () => request()->get('patient_id'))
                            ->disabled(fn () => request()->has('file_id'))
                            ->dehydrated(),

                        Forms\Components\Select::make('bank_account_id')
                            ->relationship('bankAccount', 'beneficiary_name')
                            ->options(function () {
                                return BankAccount::where('type', 'Internal')->pluck('beneficiary_name', 'id');
                            })
                            ->nullable(),

                        Forms\Components\DatePicker::make('invoice_date')
                            ->default(now()->format('Y-m-d')),

                        Forms\Components\Select::make('status')
                            ->options([
                                'Draft' => 'Draft',
                                'Posted' => 'Posted',
                                'Sent' => 'Sent',
                                'Unpaid' => 'Unpaid',
                                'Partial' => 'Partial',
                                'Paid' => 'Paid',
                            ])->default('Draft')
                            ->required(),

                        ])->columnSpan(['lg' => 2]),


                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\Placeholder::make('gop_in_total')
                            ->label('GOP In Total')
                            ->content(fn (?Invoice $record): string => $record ? '€'.number_format($record->file->gopInTotal(), 2) : '0.00'),

                        Forms\Components\Placeholder::make('bill_details')
                            ->label('Bill Details')
                            ->html()
                            ->content(function (?Invoice $record): string {
                                if (!$record || !$record->file) {
                                    return '<p class="text-sm text-gray-500">No bill items found</p>';
                                }
                                
                                // Eager load bills with their items
                                $bills = $record->file->bills()->with('items')->get();
                                $allBillItems = collect();
                                $billWithDocument = null;
                                
                                foreach ($bills as $bill) {
                                    foreach ($bill->items as $item) {
                                        $allBillItems->push([
                                            'description' => $item->description,
                                            'amount' => $item->amount,
                                        ]);
                                    }
                                    
                                    // Find first bill with document for PDF link
                                    if (!$billWithDocument && $bill->hasLocalDocument()) {
                                        $billWithDocument = $bill;
                                    }
                                }
                                
                                if ($allBillItems->isEmpty()) {
                                    return '<p class="text-sm text-gray-500">No bill items found</p>';
                                }
                                
                                $html = '<div class="space-y-2">';
                                $html .= '<div class="space-y-1">';
                                
                                foreach ($allBillItems as $index => $item) {
                                    $html .= '<div class="text-sm">';
                                    $html .= '<span>' . ($index + 1) . ')</span>';
                                    $html .= '<span> ' . e($item['description']) . ' </span>';
                                    $html .= '<span>' . number_format($item['amount'], 2) . '€</span>';
                                    $html .= '</div>';
                                }
                                
                                $html .= '</div>';
                                $html .= '<div class="pt-2 border-t border-gray-200">';
                                $html .= '<div class="text-sm font-semibold">';
                                $html .= 'Total ' . number_format($record->file->billsTotal(), 2) . '€';
                                $html .= '</div>';
                                $html .= '</div>';
                                
                                if ($billWithDocument) {
                                    $url = asset('storage/' . $billWithDocument->bill_document_path);
                                    $html .= '<div class="pt-2">';
                                    $html .= '<a href="' . e($url) . '" target="_blank" class="text-sm text-blue-600 hover:text-blue-800 underline font-medium transition-colors duration-200">View Bill</a>';
                                    $html .= '</div>';
                                }
                                
                                $html .= '</div>';
                                
                                return $html;
                            })
                            ->extraAttributes(['class' => 'prose max-w-none']),

                        Forms\Components\Placeholder::make('due_date')
                            ->label('Due date')
                            ->content(fn (?Invoice $record): string => $record ? '(' . $record->due_date->format('d/m/Y') . ')' . ' - ' . abs((int)$record->due_date->diffInDays(now())) . ' days' : '-'),

                        Forms\Components\Placeholder::make('subtotal')
                            ->label('Subtotal')
                            // lets add € sign before the subtotal
                            ->content(fn (?Invoice $record): string => $record ? '€'.number_format($record->subtotal, 2) : '0.00'),

                        Forms\Components\Placeholder::make('discount')
                            ->label('Discount')
                            ->content(fn (?Invoice $record): string => $record ? '€'.number_format($record->discount, 2) : '0.00'),

                        Forms\Components\Placeholder::make('total_amount')
                            ->label('Total Amount')
                            ->content(fn (?Invoice $record): string => $record ? '€'.number_format($record->total_amount, 2) : '0.00'),

                    ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table->groups([
            Group::make('patient.client.company_name')->collapsible(),
            Group::make('patient.name')->collapsible(),
             ])
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('file.mga_reference')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Invoice $record): string => $record->file->google_drive_link ?? '#')
                    ->openUrlInNewTab()
                    ->color(fn (Invoice $record): string => $record->file->google_drive_link ? 'primary' : 'gray'),
                Tables\Columns\TextColumn::make('patient.client.company_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('file.client_reference')
                    ->label('Client Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Client reference copied to clipboard')
                    ->copyMessageDuration(1500),
                Tables\Columns\TextColumn::make('patient.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('invoice_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'danger' => 'Unpaid',
                        'gray' => 'Draft',
                        'info' => 'Posted',
                        'success' => 'Paid',
                        'primary' => 'Sent',
                        'secondary' => 'Partial',
                    ]),
                Tables\Columns\TextColumn::make('total_amount')->money('EUR')->sortable()->summarize(Sum::make('total_amount')->label('Total Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('paid_amount')->money('EUR')->sortable()->summarize(Sum::make('paid_amount')->label('Paid Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('remaining_amount')->state(fn (Invoice $record) => $record->total_amount - $record->paid_amount)->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('file.status')->label('File Status')->badge()->color(fn (string $state): string => match ($state) {
                    'New' => 'gray',
                    'Handling' => 'info',
                    'Available' => 'info',
                    'Confirmed' => 'success',
                    'Assisted' => 'success',
                    'Hold' => 'warning',
                    'Cancelled' => 'danger',
                    'Void' => 'gray',
                }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')->relationship('patient.client', 'company_name')->label('Client')->searchable()->multiple(),
                Tables\Filters\SelectFilter::make('patient_id')->relationship('patient', 'name')->label('Patient')->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Posted' => 'Posted',
                        'Sent' => 'Sent',
                        'Unpaid' => 'Unpaid',
                        'Paid' => 'Paid',
                        'Partial' => 'Partial',
                    ]),

                Tables\Filters\Filter::make('draft_or_posted')
                    ->form([
                        Forms\Components\Checkbox::make('show_draft_posted')
                            ->label('Show Draft & Posted Invoices Only')
                            ->default(true),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['show_draft_posted'] ?? true,
                            fn (Builder $query): Builder => $query->whereIn('status', ['Draft', 'Posted']),
                        );
                    }),

                Tables\Filters\Filter::make('due_date')
                    ->form([
                        Forms\Components\DatePicker::make('due_from'),
                        Forms\Components\DatePicker::make('due_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date),
                            )
                            ->when(
                                $data['due_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('generate')
                    ->modalHeading('Generate Invoice')
                    ->modalSubmitActionLabel('Generate')
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-up')
                    ->requiresConfirmation()
                    ->modalDescription('This will generate and upload the invoice to Google Drive.')
                    ->visible(fn (Invoice $record): bool => $record->status === 'Draft')
                    ->action(function (Invoice $record) {
                        // First generate PDF
                        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $record]);
                        $content = $pdf->output();
                        $fileName = $record->name . '.pdf';

                        // Save to local storage using DocumentPathResolver
                        $resolver = app(\App\Services\DocumentPathResolver::class);
                        $localPath = $resolver->ensurePathFor($record->file, 'invoices', $fileName);
                        \Illuminate\Support\Facades\Storage::disk('public')->put($localPath, $content);
                        
                        // Update invoice with local document path
                        $record->invoice_document_path = $localPath;

                        // Upload to Google Drive (keep as secondary)
                        $uploader = app(UploadInvoiceToGoogleDrive::class);
                        $result = $uploader->uploadInvoiceToGoogleDrive(
                            $content,
                            $fileName,
                            $record
                        );

                        if ($result !== false) {
                            // Update invoice with Google Drive link if upload successful
                            $record->invoice_google_link = $result['webViewLink'];
                        }

                        $record->status = 'Posted';
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Invoice generated and uploaded successfully')
                            ->body('Invoice has been uploaded to Google Drive.')
                            ->send();
                    }),
                Action::make('markAsSent')
                    ->label('Mark as Sent')
                    ->color('primary')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalHeading('Mark Invoice as Sent')
                    ->modalDescription('Are you sure you want to mark this invoice as Sent?')
                    ->modalSubmitActionLabel('Mark as Sent')
                    ->visible(fn (Invoice $record): bool => $record->status === 'Posted')
                    ->action(function (Invoice $record) {
                        $record->status = 'Sent';
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Invoice marked as Sent')
                            ->body('Invoice status has been updated to Sent.')
                            ->send();
                    }),
            ])->headerActions([Tables\Actions\CreateAction::make()])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            //TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ($record->name ?? 'Unknown') . ' - ' . ($record->patient?->name ?? 'Unknown Patient');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Patient' => $record->patient?->name ?? 'Unknown',
            'Client' => $record->patient?->client?->company_name ?? 'Unknown',
            'File Reference' => $record->file?->mga_reference ?? 'Unknown',
            'Status' => $record->status ?? 'Unknown',
            'Total Amount' => '€' . number_format($record->total_amount ?? 0, 2),
            'Due Date' => $record->due_date?->format('d/m/Y') ?? 'Unknown',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['patient.client', 'file']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return InvoiceResource::getUrl('edit', ['record' => $record]);
    }

    public static function isGlobalSearchDisabled(): bool
    {
        return true;
    }
}