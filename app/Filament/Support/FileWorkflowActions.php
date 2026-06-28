<?php

namespace App\Filament\Support;

use App\Models\File;
use App\Models\Gop;
use App\Models\Invoice;
use App\Services\DocumentPathResolver;
use App\Services\FileWorkflowGapService;
use App\Services\GoogleDriveFolderService;
use App\Services\UploadBillToGoogleDrive;
use App\Services\UploadGopToGoogleDrive;
use App\Services\UploadInvoiceToGoogleDrive;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileWorkflowActions
{
    public static function viewFile(): Action
    {
        return Action::make('view')
            ->url(fn (File $record): string => route('filament.admin.resources.files.edit', $record))
            ->icon('heroicon-o-eye')
            ->label('View File');
    }

    public static function uploadGop(): Action
    {
        return Action::make('upload_gop')
            ->label('Upload GOP')
            ->icon('heroicon-o-document-arrow-up')
            ->color('success')
            ->visible(fn (File $record): bool => FileWorkflowGapService::missingGop($record))
            ->requiresConfirmation()
            ->modalHeading('Upload GOP Document')
            ->modalDescription('Upload a GOP document for this file.')
            ->modalSubmitActionLabel('Upload GOP')
            ->form([
                Forms\Components\Select::make('type')
                    ->options(['In' => 'In', 'Out' => 'Out'])
                    ->required()
                    ->default('In'),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->prefix('€'),
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('status')
                    ->options([
                        'Not Sent' => 'Not Sent',
                        'Sent' => 'Sent',
                        'Updated' => 'Updated',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->default('Not Sent')
                    ->required(),
                Forms\Components\FileUpload::make('file_gop_document')
                    ->label('Upload GOP Document')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240)
                    ->required()
                    ->disk('public')
                    ->directory('gops')
                    ->visibility('public')
                    ->helperText('Upload the GOP document (PDF or image)')
                    ->preserveFilenames()
                    ->maxFiles(1),
            ])
            ->action(function (File $record, array $data): void {
                self::handleGopUpload($record, $data, createNew: true);
            });
    }

    public static function uploadGopDoc(): Action
    {
        return Action::make('upload_gop_doc')
            ->label('Upload GOP Doc')
            ->icon('heroicon-o-document-arrow-up')
            ->color('success')
            ->visible(fn (File $record): bool => FileWorkflowGapService::missingGopDoc($record))
            ->requiresConfirmation()
            ->modalHeading(fn (File $record): string => "Upload GOP for {$record->mga_reference}")
            ->modalDescription(fn (File $record): string => 'Patient: '.($record->patient->name ?? 'N/A'))
            ->modalSubmitActionLabel('Upload Document')
            ->form([
                Forms\Components\FileUpload::make('gop_document')
                    ->label('Upload GOP Document')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240)
                    ->required()
                    ->disk('public')
                    ->directory('gops')
                    ->visibility('public')
                    ->helperText('Upload the GOP document (PDF or image)')
                    ->preserveFilenames()
                    ->maxFiles(1),
            ])
            ->action(function (File $record, array $data): void {
                $gop = FileWorkflowGapService::firstGopInNeedingDocument($record);

                if (! $gop) {
                    Notification::make()
                        ->danger()
                        ->title('No GOP found')
                        ->body('Could not find an In GOP missing a document on this file.')
                        ->send();

                    return;
                }

                self::handleGopDocUpload($gop, $data);
            });
    }

    public static function uploadBill(): Action
    {
        return Action::make('upload_bill')
            ->label('Upload Bill')
            ->icon('heroicon-o-document-arrow-up')
            ->color('success')
            ->visible(fn (File $record): bool => FileWorkflowGapService::missingBill($record))
            ->requiresConfirmation()
            ->modalHeading('Upload Bill Document')
            ->modalDescription('Upload a bill document for this file.')
            ->modalSubmitActionLabel('Upload Bill')
            ->form([
                Forms\Components\TextInput::make('name')
                    ->label('Bill Name')
                    ->required()
                    ->default(fn (File $record) => $record->mga_reference.'-Bill-01'),
                Forms\Components\TextInput::make('total_amount')
                    ->label('Total Amount')
                    ->numeric()
                    ->required()
                    ->prefix('€'),
                Forms\Components\DatePicker::make('due_date')
                    ->label('Due Date')
                    ->required()
                    ->default(now()->addDays(60)),
                Forms\Components\Select::make('status')
                    ->options([
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                        'Paid' => 'Paid',
                    ])
                    ->default('Unpaid')
                    ->required(),
                Forms\Components\FileUpload::make('file_bill_document')
                    ->label('Upload Bill Document')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240)
                    ->nullable()
                    ->disk('public')
                    ->directory('bills')
                    ->visibility('public')
                    ->helperText('Upload the bill document (PDF or image) - Optional')
                    ->preserveFilenames()
                    ->maxFiles(1),
            ])
            ->action(function (File $record, array $data): void {
                try {
                    $bill = new \App\Models\Bill([
                        'file_id' => $record->id,
                        'name' => $data['name'],
                        'total_amount' => $data['total_amount'],
                        'due_date' => $data['due_date'],
                        'status' => $data['status'],
                    ]);
                    $bill->save();

                    $uploadedFile = self::unwrapUploadedFile($data['file_bill_document'] ?? null);

                    if (! $uploadedFile) {
                        Notification::make()
                            ->success()
                            ->title('Bill created')
                            ->body('Bill record created without a document.')
                            ->send();

                        return;
                    }

                    $content = Storage::disk('public')->get($uploadedFile);

                    if ($content === false) {
                        Notification::make()->danger()->title('File not found')->send();

                        return;
                    }

                    $originalExtension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
                    $fileName = 'Bill '.$record->mga_reference.' - '.$record->patient->name.'.'.$originalExtension;

                    $resolver = app(DocumentPathResolver::class);
                    $localPath = $resolver->ensurePathFor($record, 'bills', $fileName);
                    Storage::disk('public')->put($localPath, $content);
                    $bill->bill_document_path = $localPath;

                    $uploadService = new UploadBillToGoogleDrive(new GoogleDriveFolderService());
                    $uploadResult = $uploadService->uploadBillToGoogleDrive($content, $fileName, $bill);

                    if ($uploadResult) {
                        $bill->bill_google_link = $uploadResult;
                    }

                    $bill->save();

                    Notification::make()
                        ->success()
                        ->title('Bill uploaded successfully')
                        ->body('Bill document has been saved locally and uploaded to Google Drive.')
                        ->send();
                } catch (\Throwable $e) {
                    Log::error('Bill creation error', ['error' => $e->getMessage(), 'file_id' => $record->id]);
                    Notification::make()->danger()->title('Bill creation error')->body($e->getMessage())->send();
                }
            });
    }

    public static function createInvoice(): Action
    {
        return Action::make('create_invoice')
            ->label('Generate Invoice')
            ->icon('heroicon-o-document-plus')
            ->color('success')
            ->visible(fn (File $record): bool => FileWorkflowGapService::missingInvoice($record))
            ->requiresConfirmation()
            ->modalHeading('Generate Invoice')
            ->modalDescription('Create the invoice with bill items and the correct file fee in one step.')
            ->modalSubmitActionLabel('Generate')
            ->action(function (File $record) {
                try {
                    $invoice = app(\App\Services\InvoiceBuilderService::class)->buildFromFile($record);

                    Notification::make()
                        ->success()
                        ->title('Invoice generated')
                        ->body("Invoice {$invoice->name} was created with bill items and file fee.")
                        ->send();

                    return redirect(\App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $invoice]));
                } catch (\Illuminate\Validation\ValidationException $e) {
                    Notification::make()
                        ->warning()
                        ->title('Could not generate invoice')
                        ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                        ->send();
                } catch (\Throwable $e) {
                    Log::error('Invoice generation error', ['error' => $e->getMessage(), 'file_id' => $record->id]);
                    Notification::make()->danger()->title('Invoice generation failed')->body($e->getMessage())->send();
                }
            });
    }

    public static function editInvoiceNeedingDoc(): Action
    {
        return Action::make('edit_invoice')
            ->label('Edit Invoice')
            ->icon('heroicon-o-document-text')
            ->color('primary')
            ->visible(fn (File $record): bool => FileWorkflowGapService::missingInvoiceDocument($record))
            ->url(function (File $record): ?string {
                $invoice = FileWorkflowGapService::firstInvoiceNeedingDocument($record);

                return $invoice
                    ? route('filament.admin.resources.invoices.edit', $invoice)
                    : null;
            });
    }

    public static function generateInvoiceDocument(): Action
    {
        return Action::make('generate_invoice_document')
            ->label('Generate Invoice Doc')
            ->icon('heroicon-o-document-arrow-up')
            ->color('success')
            ->visible(fn (File $record): bool => FileWorkflowGapService::missingInvoiceDocument($record))
            ->requiresConfirmation()
            ->modalHeading('Generate Invoice Document')
            ->modalDescription('Generate the invoice PDF and upload it to Google Drive.')
            ->modalSubmitActionLabel('Generate')
            ->action(function (File $record): void {
                $invoice = FileWorkflowGapService::firstInvoiceNeedingDocument($record);

                if (! $invoice) {
                    Notification::make()->danger()->title('No invoice found')->send();

                    return;
                }

                try {
                    $pdf = Pdf::loadView('pdf.invoice', \App\Support\InvoicePdfView::data($invoice));
                    $content = $pdf->output();
                    $fileName = $invoice->name.'.pdf';

                    $resolver = app(DocumentPathResolver::class);
                    $localPath = $resolver->ensurePathFor($record, 'invoices', $fileName);
                    Storage::disk('public')->put($localPath, $content);
                    $invoice->invoice_document_path = $localPath;

                    $uploader = app(UploadInvoiceToGoogleDrive::class);
                    $result = $uploader->uploadInvoiceToGoogleDrive($content, $fileName, $invoice);

                    if ($result !== false) {
                        $invoice->invoice_google_link = $result['webViewLink'];
                    }

                    if ($invoice->status === 'Draft') {
                        $invoice->status = 'Posted';
                    }

                    $invoice->save();

                    Notification::make()
                        ->success()
                        ->title('Invoice document generated')
                        ->body('Invoice PDF has been generated and uploaded.')
                        ->send();
                } catch (\Throwable $e) {
                    Log::error('Invoice generate error', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id]);
                    Notification::make()->danger()->title('Generation failed')->body($e->getMessage())->send();
                }
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function handleGopUpload(File $record, array $data, bool $createNew): void
    {
        try {
            $uploadedFile = self::unwrapUploadedFile($data['file_gop_document'] ?? null);

            if (! $uploadedFile) {
                Notification::make()->danger()->title('No document uploaded')->send();

                return;
            }

            $content = Storage::disk('public')->get($uploadedFile);

            if ($content === false) {
                Notification::make()->danger()->title('File not found')->send();

                return;
            }

            $originalExtension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
            $fileName = 'GOP '.$data['type'].' '.$record->mga_reference.' - '.$record->patient->name.'.'.$originalExtension;

            $gop = new Gop([
                'file_id' => $record->id,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'date' => $data['date'],
                'status' => $data['status'],
            ]);
            $gop->save();

            self::uploadGopContent($gop, $content, $fileName, 'GOP uploaded successfully');
        } catch (\Throwable $e) {
            Log::error('GOP upload error', ['error' => $e->getMessage(), 'file_id' => $record->id]);
            Notification::make()->danger()->title('Upload error')->body($e->getMessage())->send();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function handleGopDocUpload(Gop $gop, array $data): void
    {
        try {
            $uploadedFile = self::unwrapUploadedFile($data['gop_document'] ?? null);

            if (! $uploadedFile) {
                Notification::make()->danger()->title('No document uploaded')->send();

                return;
            }

            $content = Storage::disk('public')->get($uploadedFile);

            if ($content === false) {
                Notification::make()->danger()->title('File not found')->send();

                return;
            }

            $gop->loadMissing('file.patient');
            $originalExtension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
            $fileName = 'GOP '.$gop->type.' '.$gop->file->mga_reference.' - '.$gop->file->patient->name.'.'.$originalExtension;

            self::uploadGopContent($gop, $content, $fileName, 'GOP document uploaded successfully');
        } catch (\Throwable $e) {
            Log::error('GOP doc upload error', ['error' => $e->getMessage(), 'gop_id' => $gop->id]);
            Notification::make()->danger()->title('Upload error')->body($e->getMessage())->send();
        }
    }

    protected static function uploadGopContent(Gop $gop, string $content, string $fileName, string $successTitle): void
    {
        $uploadService = new UploadGopToGoogleDrive(new GoogleDriveFolderService());
        $uploadResult = $uploadService->uploadGopToGoogleDrive($content, $fileName, $gop);

        if ($uploadResult) {
            $gop->gop_google_drive_link = $uploadResult;
            $gop->status = 'Sent';
            $gop->save();

            Notification::make()->success()->title($successTitle)->send();

            return;
        }

        Notification::make()->danger()->title('Google Drive upload failed')->send();
    }

    protected static function unwrapUploadedFile(mixed $uploadedFile): ?string
    {
        if (is_array($uploadedFile)) {
            $uploadedFile = $uploadedFile[0] ?? null;
        }

        return is_string($uploadedFile) && $uploadedFile !== '' ? $uploadedFile : null;
    }
}
