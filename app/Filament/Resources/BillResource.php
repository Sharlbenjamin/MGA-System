<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillResource\Pages;
use App\Filament\Resources\BillResource\RelationManagers\ItemsRelationManager;
use App\Models\BankAccount;
use App\Models\Bill;
use Filament\Forms;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Database\Eloquent\Builder;
use App\Services\UploadBillToGoogleDrive;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;

class BillResource extends Resource
{
    protected static ?string $model = Bill::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = 'Finance';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')->maxLength(255),
                        Forms\Components\Select::make('file_id')
                            ->relationship('file', 'mga_reference')
                            ->required()
                            ->searchable()
                            ->default(fn () => request()->get('file_id'))
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('provider_id')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->disabled()
                            ->live()
                            ->dehydrated()
                            ->reactive()
                            ->afterStateHydrated(function ($state, $set, $get) {
                                $fileId = $get('file_id');
                                if ($fileId) {
                                    $file = \App\Models\File::find($fileId);
                                    if ($file && $file->providerBranch) {
                                        $set('provider_id', $file->providerBranch->provider_id);
                                    }
                                }
                            }),
                        Forms\Components\Select::make('branch_id')
                            ->relationship('branch', 'branch_name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->disabled()
                            ->live()
                            ->dehydrated()
                            ->reactive()
                            ->afterStateHydrated(function ($state, $set, $get) {
                                $fileId = $get('file_id');
                                if ($fileId) {
                                    $file = \App\Models\File::find($fileId);
                                    if ($file) {
                                        $set('branch_id', $file->provider_branch_id);
                                    }
                                }
                            }),
                        Forms\Components\Select::make('bank_account_id')
                            ->relationship('bankAccount', 'beneficiary_name')
                            ->options(function () {
                                return BankAccount::where('type', 'internal')->pluck('beneficiary_name', 'id');
                            })
                            ->nullable(),
                        Forms\Components\DatePicker::make('bill_date')->default(now()->format('Y-m-d')),
                        Forms\Components\Select::make('status')
                            ->options([
                                'Unpaid' => 'Unpaid',
                                'Partial' => 'Partial',
                                'Paid' => 'Paid',
                            ])->default('Unpaid')
                            ->required(),
                        Forms\Components\TextInput::make('bill_google_link')
                            ->label('Google Drive Link')
                            ->url()
                            ->helperText('Google Drive link for this bill'),
                        // Forms\Components\FileUpload::make('document')
                        //     ->label('Bill Document')
                        //     ->acceptedFileTypes(['application/pdf', 'image/*'])
                        //     ->maxSize(10240) // 10MB
                        //     ->disk('public')
                        //     ->directory('bills')
                        //     ->visibility('public')
                        //     ->helperText('Upload the bill document (PDF or image)')
                        //     ->downloadable()
                        //     ->previewable(),
                    ])->columnSpan(['lg' => 2]),
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')->label('Created at')->content(fn (?Bill $record): string => $record ? $record->created_at->diffForHumans() : '-'),
                        Forms\Components\Placeholder::make('due_date')->label('Due date')->content(fn (?Bill $record): string => $record ? '(' . $record->due_date->format('d/m/Y') . ')' . ' - ' . abs((int)$record->due_date->diffInDays(now())) . ' days' : '-'),
                        Forms\Components\Placeholder::make('subtotal')->label('Subtotal')->content(fn (?Bill $record): string => $record ? '€'.number_format($record->subtotal, 2) : '0.00'),
                        Forms\Components\Placeholder::make('discount')->label('Discount')->content(fn (?Bill $record): string => $record ? '€'.number_format($record->discount, 2) : '0.00'),
                        Forms\Components\Placeholder::make('total_amount')->label('Total Amount')->content(fn (?Bill $record): string => $record ? '€'.number_format($record->total_amount, 2) : '0.00'),

                    ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table->groups([
            Group::make('provider.name')->label('Provider')->collapsible(),
            Group::make('branch.branch_name')->label('Branch')->collapsible(),
        ])
            ->defaultSort('bill_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('provider.name')->searchable()->sortable()->label('Provider'),
                Tables\Columns\TextColumn::make('branch.branch_name')->searchable()->sortable()->label('Branch'),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('file.mga_reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable(),
                Tables\Columns\BadgeColumn::make('status')->colors(['danger' => 'Unpaid','success' => 'Paid','primary' => 'Partial',])->summarize(Count::make('status')->label('Number of Bills')),
                Tables\Columns\TextColumn::make('total_amount')->money('EUR')->sortable()->summarize(Sum::make('total_amount')->label('Total Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('paid_amount')->money('EUR')->sortable()->summarize(Sum::make('paid_amount')->label('Paid Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('remaining_amount')->money('EUR')->sortable()->state(fn (Bill $record) => $record->total_amount - $record->paid_amount),
                Tables\Columns\TextColumn::make('file.status')->label('File Status')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('bill_google_link')
                    ->label('Google Drive')
                    ->url(fn (Bill $record) => $record->bill_google_link)
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('document')
                    ->label('Document')
                    ->url(fn (Bill $record) => $record->document ? Storage::disk('public')->url($record->document) : null)
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider.name')->relationship('provider', 'name')->label('Provider')->searchable()->multiple(),
                Tables\Filters\SelectFilter::make('branch.branch_name')->relationship('branch', 'branch_name')->label('Branch')->searchable()->multiple(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Unpaid' => 'Unpaid',
                        'Paid' => 'Paid',
                        'Partial' => 'Partial',
                    ]),

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
                Action::make('upload_to_google_drive')
                    ->label('Upload')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Upload Bill to Google Drive')
                    ->modalDescription('This will upload a bill document to the Google Drive folder associated with this file.')
                    ->modalSubmitActionLabel('Upload')
                    ->form([
                        Forms\Components\FileUpload::make('document')
                            ->label('Upload Bill Document')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->disk('public')
                            ->directory('bills')
                            ->visibility('public')
                            ->helperText('Upload the bill document (PDF or image)'),
                    ])
                    ->action(function (Bill $record, array $data) {
                        // Get the file content using the public disk
                        $filePath = storage_path('app/public/bills/' . basename($data['document']));
                        if (!file_exists($filePath)) {
                            Notification::make()
                                ->danger()
                                ->title('File not found')
                                ->body('The uploaded file could not be found.')
                                ->send();
                            return;
                        }

                        $fileContent = file_get_contents($filePath);
                        $fileName = basename($data['document']);

                        // Upload to Google Drive
                        $uploader = app(UploadBillToGoogleDrive::class);
                        $result = $uploader->uploadBillToGoogleDrive(
                            $fileContent,
                            $fileName,
                            $record
                        );

                        if ($result === false) {
                            Notification::make()
                                ->danger()
                                ->title('Upload failed')
                                ->body('Failed to upload to Google Drive. Check logs for more details.')
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Upload successful')
                            ->body('Bill has been uploaded to Google Drive successfully.')
                            ->send();
                    })
                    ->visible(fn (Bill $record) => !$record->bill_google_link),
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (Bill $record) => $record->draft_path)
                    ->openUrlInNewTab(),
            ])->headerActions([Tables\Actions\CreateAction::make()])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBills::route('/'),
            'create' => Pages\CreateBill::route('/create'),
            'edit' => Pages\EditBill::route('/{record}/edit'),
        ];
    }
}