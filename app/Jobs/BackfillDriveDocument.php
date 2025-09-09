<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\GoogleDriveFileDownloader;
use App\Services\DocumentPathResolver;
use App\Models\File;
use App\Models\BackfillLog;
use Exception;

class BackfillDriveDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $backoff = [30, 60, 120]; // 30s, 1m, 2m

    protected string $modelClass;
    protected int $modelId;
    protected string $field;
    protected string $category;
    protected string $googleLink;

    /**
     * Create a new job instance.
     */
    public function __construct(string $modelClass, int $modelId, string $field, string $category, string $googleLink)
    {
        $this->modelClass = $modelClass;
        $this->modelId = $modelId;
        $this->field = $field;
        $this->category = $category;
        $this->googleLink = $googleLink;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $logEntry = null;
        
        try {
            Log::info('Starting backfill job', [
                'model_class' => $this->modelClass,
                'model_id' => $this->modelId,
                'field' => $this->field,
                'category' => $this->category,
                'google_link' => $this->googleLink
            ]);

            // Create or update log entry
            $logEntry = BackfillLog::updateOrCreate(
                [
                    'model_class' => $this->modelClass,
                    'model_id' => $this->modelId,
                    'field' => $this->field,
                ],
                [
                    'category' => $this->category,
                    'google_link' => $this->googleLink,
                    'status' => 'retrying',
                    'attempts' => $this->attempts(),
                    'last_attempt_at' => now(),
                ]
            );

            // Get the model instance
            $model = $this->modelClass::find($this->modelId);
            if (!$model) {
                throw new Exception("Model not found: {$this->modelClass} ID {$this->modelId}");
            }

            // Check if local document already exists
            if (!empty($model->{$this->field})) {
                Log::info('Local document already exists, skipping', [
                    'model_class' => $this->modelClass,
                    'model_id' => $this->modelId,
                    'local_path' => $model->{$this->field}
                ]);
                return;
            }

            // Get the file relationship
            $file = $this->getFileFromModel($model);
            if (!$file) {
                throw new Exception("No file relationship found for model: {$this->modelClass} ID {$this->modelId}");
            }

            // Extract file ID from Google Drive URL
            $fileId = $this->extractFileIdFromUrl($this->googleLink);
            if (!$fileId) {
                throw new Exception("Could not extract file ID from Google Drive URL: {$this->googleLink}");
            }

            // Download file from Google Drive
            $downloader = app(GoogleDriveFileDownloader::class);
            $result = $downloader->downloadByFileId($fileId);

            if (!$result['ok'] || empty($result['contents'])) {
                throw new Exception("Failed to download from Google Drive: " . ($result['error'] ?? 'Unknown error'));
            }

            // Validate content
            $this->validateContent($result['contents'], $result['contentType'] ?? '');

            // Generate filename
            $filename = $this->generateFilename($model, $result['filename'] ?? '', $result['extension'] ?? 'pdf');

            // Store file locally
            $resolver = app(DocumentPathResolver::class);
            $localPath = $resolver->ensurePathFor($file, $this->category, $filename);
            
            Storage::disk('public')->put($localPath, $result['contents']);

            // Update model with local path
            $model->update([$this->field => $localPath]);

            // Mark log entry as successful
            if ($logEntry) {
                $logEntry->markAsSuccess();
            }

            Log::info('Successfully backfilled document', [
                'model_class' => $this->modelClass,
                'model_id' => $this->modelId,
                'local_path' => $localPath,
                'file_size' => strlen($result['contents']),
                'content_type' => $result['contentType'] ?? 'unknown'
            ]);

        } catch (Exception $e) {
            // Update log entry with error
            if ($logEntry) {
                $logEntry->markAsFailed($e->getMessage());
            }

            Log::error('Backfill job failed', [
                'model_class' => $this->modelClass,
                'model_id' => $this->modelId,
                'field' => $this->field,
                'category' => $this->category,
                'google_link' => $this->googleLink,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Log to daily log file as well
            Log::channel('daily')->error('Backfill Drive Document Failed', [
                'model_class' => $this->modelClass,
                'model_id' => $this->modelId,
                'field' => $this->field,
                'category' => $this->category,
                'google_link' => $this->googleLink,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'timestamp' => now()->toISOString()
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Get the file relationship from the model
     */
    protected function getFileFromModel($model): ?File
    {
        // Handle different model types
        if (method_exists($model, 'file')) {
            return $model->file;
        }

        if (method_exists($model, 'files')) {
            return $model->files->first();
        }

        // For transactions, get file through related model
        if ($model instanceof \App\Models\Transaction && $model->related_type === 'File') {
            return File::find($model->related_id);
        }

        return null;
    }

    /**
     * Extract file ID from Google Drive URL
     */
    protected function extractFileIdFromUrl(string $url): ?string
    {
        // Handle different Google Drive URL formats
        if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Validate downloaded content
     */
    protected function validateContent(string $content, string $contentType): void
    {
        // Check if content is empty
        if (empty($content)) {
            throw new Exception('Downloaded content is empty');
        }

        // Check minimum size (at least 100 bytes)
        if (strlen($content) < 100) {
            throw new Exception('Downloaded content is too small: ' . strlen($content) . ' bytes');
        }

        // Validate content type
        $allowedTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        if (!empty($contentType) && !in_array($contentType, $allowedTypes)) {
            throw new Exception("Invalid content type: {$contentType}");
        }

        // Check if it looks like a PDF (should start with %PDF)
        if (substr($content, 0, 4) === '%PDF') {
            return; // Valid PDF
        }

        // Check if it looks like an image
        $imageSignatures = [
            "\xFF\xD8\xFF", // JPEG
            "\x89PNG\r\n\x1a\n", // PNG
            "GIF87a", // GIF
            "GIF89a", // GIF
            "RIFF", // WebP (starts with RIFF)
        ];

        foreach ($imageSignatures as $signature) {
            if (strpos($content, $signature) === 0) {
                return; // Valid image
            }
        }

        // If we get here, it's not a recognized file type
        throw new Exception('Downloaded content does not appear to be a valid PDF or image file');
    }

    /**
     * Generate filename for the document
     */
    protected function generateFilename($model, string $originalFilename, string $extension): string
    {
        // Get base name from model
        $baseName = $this->getModelBaseName($model);
        
        // Clean the base name
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        $baseName = preg_replace('/_+/', '_', $baseName);
        $baseName = trim($baseName, '_');

        // Use original filename if available and clean
        if (!empty($originalFilename)) {
            $originalBase = pathinfo($originalFilename, PATHINFO_FILENAME);
            $originalBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalBase);
            if (!empty($originalBase)) {
                $baseName = $originalBase;
            }
        }

        // Ensure we have an extension
        if (empty($extension)) {
            $extension = 'pdf';
        }

        return "{$baseName}.{$extension}";
    }

    /**
     * Get base name from model for filename generation
     */
    protected function getModelBaseName($model): string
    {
        // Try to get a meaningful name from the model
        if (isset($model->name)) {
            return $model->name;
        }

        if (isset($model->title)) {
            return $model->title;
        }

        // For file-related models, try to get file reference
        if (method_exists($model, 'file') && $model->file) {
            return $model->file->mga_reference ?? 'Document';
        }

        // Fallback to model class and ID
        $className = class_basename($this->modelClass);
        return "{$className}_{$this->modelId}";
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        // Update log entry for permanent failure
        BackfillLog::where('model_class', $this->modelClass)
            ->where('model_id', $this->modelId)
            ->where('field', $this->field)
            ->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'attempts' => $this->attempts(),
                'last_attempt_at' => now(),
            ]);

        Log::error('Backfill job permanently failed', [
            'model_class' => $this->modelClass,
            'model_id' => $this->modelId,
            'field' => $this->field,
            'category' => $this->category,
            'google_link' => $this->googleLink,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Log to daily log file for permanent failures
        Log::channel('daily')->error('Backfill Drive Document Permanently Failed', [
            'model_class' => $this->modelClass,
            'model_id' => $this->modelId,
            'field' => $this->field,
            'category' => $this->category,
            'google_link' => $this->googleLink,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'timestamp' => now()->toISOString()
        ]);
    }
}
