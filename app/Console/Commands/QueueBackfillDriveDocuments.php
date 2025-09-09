<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Jobs\BackfillDriveDocument;
use App\Models\Invoice;
use App\Models\Bill;
use App\Models\Gop;
use App\Models\MedicalReport;
use App\Models\Prescription;
use App\Models\Transaction;

class QueueBackfillDriveDocuments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backfill:drive-documents 
                            {--type=* : Document types to process (invoices, bills, gops, medical_reports, prescriptions, transactions)}
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--chunk=100 : Number of records to process per chunk}
                            {--dry-run : Show what would be processed without actually queuing jobs}
                            {--force : Process records even if they already have local documents}';

    /**
     * The console command description.
     */
    protected $description = 'Queue jobs to backfill Google Drive documents to local storage';

    /**
     * Document type configurations
     */
    protected array $documentTypes = [
        'invoices' => [
            'model' => Invoice::class,
            'field' => 'invoice_document_path',
            'google_field' => 'invoice_google_link',
            'category' => 'invoices',
            'date_field' => 'invoice_date'
        ],
        'bills' => [
            'model' => Bill::class,
            'field' => 'bill_document_path',
            'google_field' => 'bill_google_link',
            'category' => 'bills',
            'date_field' => 'bill_date'
        ],
        'gops' => [
            'model' => Gop::class,
            'field' => 'document_path',
            'google_field' => 'gop_google_drive_link',
            'category' => 'gops',
            'date_field' => 'date'
        ],
        'medical_reports' => [
            'model' => MedicalReport::class,
            'field' => 'document_path',
            'google_field' => null, // Medical reports don't have Google Drive links
            'category' => 'medical_reports',
            'date_field' => 'date'
        ],
        'prescriptions' => [
            'model' => Prescription::class,
            'field' => 'document_path',
            'google_field' => null, // Prescriptions don't have Google Drive links
            'category' => 'prescriptions',
            'date_field' => 'date'
        ],
        'transactions' => [
            'model' => Transaction::class,
            'field' => 'attachment_path',
            'google_field' => null, // Transactions use attachment_path for Google Drive links
            'category' => 'transactions',
            'date_field' => 'date'
        ]
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $types = $this->option('type') ?: array_keys($this->documentTypes);
        $fromDate = $this->option('from') ? Carbon::parse($this->option('from')) : null;
        $toDate = $this->option('to') ? Carbon::parse($this->option('to')) : null;
        $chunkSize = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Starting backfill process...');
        $this->info('Types: ' . implode(', ', $types));
        if ($fromDate) $this->info('From: ' . $fromDate->format('Y-m-d'));
        if ($toDate) $this->info('To: ' . $toDate->format('Y-m-d'));
        $this->info('Chunk size: ' . $chunkSize);
        $this->info('Dry run: ' . ($dryRun ? 'Yes' : 'No'));
        $this->info('Force: ' . ($force ? 'Yes' : 'No'));

        $totalQueued = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($types as $type) {
            if (!isset($this->documentTypes[$type])) {
                $this->error("Unknown document type: {$type}");
                $totalErrors++;
                continue;
            }

            $this->info("\nProcessing {$type}...");
            
            try {
                $result = $this->processDocumentType(
                    $type,
                    $fromDate,
                    $toDate,
                    $chunkSize,
                    $dryRun,
                    $force
                );

                $totalQueued += $result['queued'];
                $totalSkipped += $result['skipped'];
                $totalErrors += $result['errors'];

                $this->info("  Queued: {$result['queued']}");
                $this->info("  Skipped: {$result['skipped']}");
                if ($result['errors'] > 0) {
                    $this->warn("  Errors: {$result['errors']}");
                }

            } catch (\Exception $e) {
                $this->error("Error processing {$type}: " . $e->getMessage());
                $totalErrors++;
            }
        }

        $this->info("\n" . str_repeat('=', 50));
        $this->info("Summary:");
        $this->info("Total queued: {$totalQueued}");
        $this->info("Total skipped: {$totalSkipped}");
        if ($totalErrors > 0) {
            $this->warn("Total errors: {$totalErrors}");
        }

        if ($dryRun) {
            $this->info("\nThis was a dry run. No jobs were actually queued.");
        } else {
            $this->info("\nJobs have been queued. Monitor the queue for progress.");
        }

        return $totalErrors > 0 ? 1 : 0;
    }

    /**
     * Process a specific document type
     */
    protected function processDocumentType(
        string $type,
        ?Carbon $fromDate,
        ?Carbon $toDate,
        int $chunkSize,
        bool $dryRun,
        bool $force
    ): array {
        $config = $this->documentTypes[$type];
        $model = $config['model'];
        $field = $config['field'];
        $googleField = $config['google_field'];
        $category = $config['category'];
        $dateField = $config['date_field'];

        $queued = 0;
        $skipped = 0;
        $errors = 0;

        // Build query
        $query = $model::query();

        // Add date filters
        if ($fromDate) {
            $query->where($dateField, '>=', $fromDate);
        }
        if ($toDate) {
            $query->where($dateField, '<=', $toDate);
        }

        // Add Google Drive link filter
        if ($googleField) {
            $query->whereNotNull($googleField)
                  ->where($googleField, '!=', '');
        } else {
            // For models without specific Google Drive fields, check if attachment_path contains Google Drive URL
            $query->whereNotNull($field)
                  ->where($field, 'like', '%drive.google.com%');
        }

        // Add local document filter (unless forcing)
        if (!$force) {
            if ($googleField) {
                $query->where(function ($q) use ($field) {
                    $q->whereNull($field)
                      ->orWhere($field, '');
                });
            } else {
                // For transactions, we want to process Google Drive links that aren't already local files
                $query->where($field, 'like', '%drive.google.com%');
            }
        }

        $totalRecords = $query->count();
        $this->info("  Found {$totalRecords} records to process");

        if ($totalRecords === 0) {
            return ['queued' => 0, 'skipped' => 0, 'errors' => 0];
        }

        // Process in chunks
        $query->chunk($chunkSize, function ($records) use (
            $type,
            $config,
            $googleField,
            $dryRun,
            &$queued,
            &$skipped,
            &$errors
        ) {
            foreach ($records as $record) {
                try {
                    $googleLink = $this->getGoogleLink($record, $config, $googleField);
                    
                    if (!$googleLink) {
                        $skipped++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  Would queue: {$type} ID {$record->id} - {$googleLink}");
                        $queued++;
                    } else {
                        BackfillDriveDocument::dispatch(
                            $config['model'],
                            $record->id,
                            $config['field'],
                            $config['category'],
                            $googleLink
                        );
                        $queued++;
                    }

                } catch (\Exception $e) {
                    $this->error("  Error processing {$type} ID {$record->id}: " . $e->getMessage());
                    $errors++;
                }
            }
        });

        return ['queued' => $queued, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Get Google Drive link from record
     */
    protected function getGoogleLink($record, array $config, ?string $googleField): ?string
    {
        if ($googleField) {
            return $record->{$googleField};
        }

        // For transactions, check if attachment_path is a Google Drive URL
        if ($config['model'] === Transaction::class) {
            $attachmentPath = $record->attachment_path;
            if ($attachmentPath && str_contains($attachmentPath, 'drive.google.com')) {
                return $attachmentPath;
            }
        }

        return null;
    }
}
