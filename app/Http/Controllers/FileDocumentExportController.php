<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use ZipArchive;
use Google\Client;
use Google\Service\Drive;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Bill;
use App\Models\Gop;
use App\Models\MedicalReport;
use App\Models\Prescription;
use App\Models\Transaction;
use App\Services\GoogleDriveFileDownloader;

class FileDocumentExportController extends Controller
{
    /**
     * Export documents for files as a ZIP archive
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportZip(Request $request)
    {
        // Validate and parse filters
        $filters = $this->parseFilters($request);
        
        // Get files based on filters
        $files = $this->getFilesWithFilters($filters);
        
        if ($files->isEmpty()) {
            return response()->json(['error' => 'No files found matching the criteria'], 404);
        }

        // Create zip file
        $zipFileName = $this->generateZipFileName($filters);
        $zipPath = storage_path('app/temp/' . $zipFileName);
        
        // Ensure temp directory exists
        $this->ensureTempDirectory();
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            Log::error('Could not create zip file', ['path' => $zipPath]);
            return response()->json(['error' => 'Could not create zip file'], 500);
        }

        $downloader = app(GoogleDriveFileDownloader::class);
        $stats = [
            'files_processed' => 0,
            'local_documents' => 0,
            'google_drive_documents' => 0,
            'missing_documents' => 0,
            'errors' => 0
        ];

        // Process each file
        foreach ($files as $file) {
            $this->processFileDocuments($file, $zip, $downloader, $stats);
            $stats['files_processed']++;
        }

        $zip->close();

        // Log summary
        Log::info('File document export completed', [
            'zip_file' => $zipFileName,
            'stats' => $stats
        ]);

        // Return the zip file
        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend();
    }

    /**
     * Parse and validate request filters
     */
    private function parseFilters(Request $request): array
    {
        $filters = [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'types' => $request->get('types', []),
            'status' => $request->get('status'),
            'patient_id' => $request->get('patient_id'),
            'client_id' => $request->get('client_id'),
        ];

        // Validate date range
        if ($filters['date_from']) {
            $filters['date_from'] = Carbon::parse($filters['date_from'])->startOfDay();
        }
        if ($filters['date_to']) {
            $filters['date_to'] = Carbon::parse($filters['date_to'])->endOfDay();
        }

        // Validate document types
        $validTypes = ['invoices', 'bills', 'gops', 'medical_reports', 'prescriptions', 'transactions'];
        if (!empty($filters['types'])) {
            $filters['types'] = array_intersect($filters['types'], $validTypes);
        } else {
            $filters['types'] = $validTypes; // Default to all types
        }

        return $filters;
    }

    /**
     * Get files based on filters
     */
    private function getFilesWithFilters(array $filters)
    {
        $query = File::with([
            'patient',
            'invoices',
            'bills', 
            'gops',
            'medicalReports',
            'prescriptions'
        ]);

        // Apply date filter
        if ($filters['date_from'] || $filters['date_to']) {
            $query->where(function ($q) use ($filters) {
                if ($filters['date_from']) {
                    $q->where('service_date', '>=', $filters['date_from']);
                }
                if ($filters['date_to']) {
                    $q->where('service_date', '<=', $filters['date_to']);
                }
            });
        }

        // Apply status filter
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        // Apply patient filter
        if ($filters['patient_id']) {
            $query->where('patient_id', $filters['patient_id']);
        }

        // Apply client filter
        if ($filters['client_id']) {
            $query->whereHas('patient', function ($q) use ($filters) {
                $q->where('client_id', $filters['client_id']);
            });
        }

        return $query->get();
    }

    /**
     * Process documents for a single file
     */
    private function processFileDocuments(File $file, ZipArchive $zip, GoogleDriveFileDownloader $downloader, array &$stats): void
    {
        $filePrefix = $file->mga_reference . '_' . $file->patient->name;
        $filePrefix = $this->sanitizeFileName($filePrefix);

        // Process each document type
        foreach (['invoices', 'bills', 'gops', 'medical_reports', 'prescriptions', 'transactions'] as $type) {
            $this->processDocumentType($file, $type, $zip, $downloader, $filePrefix, $stats);
        }
    }

    /**
     * Process documents of a specific type for a file
     */
    private function processDocumentType(File $file, string $type, ZipArchive $zip, GoogleDriveFileDownloader $downloader, string $filePrefix, array &$stats): void
    {
        switch ($type) {
            case 'invoices':
                $this->processInvoices($file, $zip, $downloader, $filePrefix, $stats);
                break;
            case 'bills':
                $this->processBills($file, $zip, $downloader, $filePrefix, $stats);
                break;
            case 'gops':
                $this->processGops($file, $zip, $downloader, $filePrefix, $stats);
                break;
            case 'medical_reports':
                $this->processMedicalReports($file, $zip, $downloader, $filePrefix, $stats);
                break;
            case 'prescriptions':
                $this->processPrescriptions($file, $zip, $downloader, $filePrefix, $stats);
                break;
            case 'transactions':
                $this->processTransactions($file, $zip, $downloader, $filePrefix, $stats);
                break;
        }
    }

    /**
     * Process invoice documents
     */
    private function processInvoices(File $file, ZipArchive $zip, GoogleDriveFileDownloader $downloader, string $filePrefix, array &$stats): void
    {
        foreach ($file->invoices as $invoice) {
            $this->processDocument(
                $invoice,
                'invoice_document_path',
                'invoice_google_link',
                $zip,
                $downloader,
                "Invoices/{$filePrefix}_Invoice_{$invoice->name}",
                $stats
            );
        }
    }

    /**
     * Process bill documents
     */
    private function processBills(File $file, ZipArchive $zip, GoogleDriveFileDownloader $downloader, string $filePrefix, array &$stats): void
    {
        foreach ($file->bills as $bill) {
            $this->processDocument(
                $bill,
                'bill_document_path',
                'bill_google_link',
                $zip,
                $downloader,
                "Bills/{$filePrefix}_Bill_{$bill->name}",
                $stats
            );
        }
    }

    /**
     * Process GOP documents
     */
    private function processGops(File $file, ZipArchive $zip, GoogleDriveFileDownloader $downloader, string $filePrefix, array &$stats): void
    {
        foreach ($file->gops as $gop) {
            $this->processDocument(
                $gop,
                'document_path',
                'gop_google_drive_link',
                $zip,
                $downloader,
                "GOPs/{$filePrefix}_GOP_{$gop->type}_{$gop->id}",
                $stats
            );
        }
    }

    /**
     * Process medical report documents
     */
    private function processMedicalReports(File $file, ZipArchive $zip, GoogleDriveFileDownloader $downloader, string $filePrefix, array &$stats): void
    {
        foreach ($file->medicalReports as $report) {
            $this->processDocument(
                $report,
                'document_path',
                null, // Medical reports don't have Google Drive links
                $zip,
                $downloader,
                "MedicalReports/{$filePrefix}_MedicalReport_{$report->id}",
                $stats
            );
        }
    }

    /**
     * Process prescription documents
     */
    private function processPrescriptions(File $file, ZipArchive $zip, GoogleDriveFileDownloader $downloader, string $filePrefix, array &$stats): void
    {
        foreach ($file->prescriptions as $prescription) {
            $this->processDocument(
                $prescription,
                'document_path',
                null, // Prescriptions don't have Google Drive links
                $zip,
                $downloader,
                "Prescriptions/{$filePrefix}_Prescription_{$prescription->name}",
                $stats
            );
        }
    }

    /**
     * Process transaction documents
     */
    private function processTransactions(File $file, ZipArchive $zip, GoogleDriveFileDownloader $downloader, string $filePrefix, array &$stats): void
    {
        $transactions = Transaction::where('related_type', 'File')
            ->where('related_id', $file->id)
            ->whereNotNull('attachment_path')
            ->get();

        foreach ($transactions as $transaction) {
            $this->processDocument(
                $transaction,
                'attachment_path',
                null, // Transactions don't have Google Drive links
                $zip,
                $downloader,
                "Transactions/{$filePrefix}_Transaction_{$transaction->type}_{$transaction->id}",
                $stats
            );
        }
    }

    /**
     * Process a single document (local or Google Drive)
     */
    private function processDocument($model, string $localPathField, ?string $googleLinkField, ZipArchive $zip, GoogleDriveFileDownloader $downloader, string $baseFileName, array &$stats): void
    {
        try {
            // Try local document first
            if (!empty($model->$localPathField)) {
                $this->addLocalDocument($model->$localPathField, $zip, $baseFileName, $stats);
                return;
            }

            // Try Google Drive if available
            if ($googleLinkField && !empty($model->$googleLinkField)) {
                $this->addGoogleDriveDocument($model->$googleLinkField, $zip, $downloader, $baseFileName, $stats);
                return;
            }

            // No document available
            $this->addMissingDocument($zip, $baseFileName, $stats);

        } catch (\Exception $e) {
            Log::error('Error processing document', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'local_path' => $model->$localPathField ?? null,
                'google_link' => $googleLinkField ? ($model->$googleLinkField ?? null) : null,
                'error' => $e->getMessage()
            ]);
            $stats['errors']++;
            $this->addMissingDocument($zip, $baseFileName, $stats, $e->getMessage());
        }
    }

    /**
     * Add local document to ZIP
     */
    private function addLocalDocument(string $relativePath, ZipArchive $zip, string $baseFileName, array &$stats): void
    {
        try {
            $fullPath = Storage::disk('public')->path($relativePath);
            
            if (!file_exists($fullPath)) {
                throw new \Exception("Local file not found: {$fullPath}");
            }

            $content = file_get_contents($fullPath);
            $extension = pathinfo($relativePath, PATHINFO_EXTENSION) ?: 'pdf';
            $fileName = "{$baseFileName}.{$extension}";
            
            $zip->addFromString($fileName, $content);
            $stats['local_documents']++;
            
            Log::debug('Added local document to ZIP', [
                'file' => $fileName,
                'path' => $relativePath
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to add local document', [
                'path' => $relativePath,
                'error' => $e->getMessage()
            ]);
            $this->addMissingDocument($zip, $baseFileName, $stats, $e->getMessage());
        }
    }

    /**
     * Add Google Drive document to ZIP
     */
    private function addGoogleDriveDocument(string $googleLink, ZipArchive $zip, GoogleDriveFileDownloader $downloader, string $baseFileName, array &$stats): void
    {
        try {
            $fileId = $this->extractFileIdFromUrl($googleLink);
            if (!$fileId) {
                throw new \Exception("Could not extract file ID from URL: {$googleLink}");
            }

            $result = $downloader->downloadByFileId($fileId);
            
            if ($result['ok'] && !empty($result['contents'])) {
                $extension = $result['extension'] ?? 'pdf';
                $fileName = "{$baseFileName}.{$extension}";
                
                $zip->addFromString($fileName, $result['contents']);
                $stats['google_drive_documents']++;
                
                Log::debug('Added Google Drive document to ZIP', [
                    'file' => $fileName,
                    'file_id' => $fileId
                ]);
            } else {
                throw new \Exception($result['error'] ?? 'Download failed');
            }

        } catch (\Exception $e) {
            Log::warning('Failed to add Google Drive document', [
                'url' => $googleLink,
                'error' => $e->getMessage()
            ]);
            $this->addMissingDocument($zip, $baseFileName, $stats, $e->getMessage());
        }
    }

    /**
     * Add missing document placeholder to ZIP
     */
    private function addMissingDocument(ZipArchive $zip, string $baseFileName, array &$stats, ?string $errorMessage = null): void
    {
        $fileName = "{$baseFileName}_MISSING.txt";
        $content = $this->createMissingDocumentFile($baseFileName, $errorMessage);
        
        $zip->addFromString($fileName, $content);
        $stats['missing_documents']++;
    }

    /**
     * Create content for missing document file
     */
    private function createMissingDocumentFile(string $documentName, ?string $errorMessage = null): string
    {
        $content = "Missing Document\n";
        $content .= "================\n\n";
        $content .= "Document: {$documentName}\n";
        $content .= "Status: Document not available\n\n";
        
        if ($errorMessage) {
            $content .= "Error: {$errorMessage}\n\n";
        }
        
        $content .= "This document could not be included in the export due to:\n";
        $content .= "- Local file not found, or\n";
        $content .= "- Google Drive download failed, or\n";
        $content .= "- Document not accessible\n\n";
        $content .= "Please check the original system for this document.\n";
        
        return $content;
    }

    /**
     * Extract file ID from Google Drive URL
     */
    private function extractFileIdFromUrl(string $url): ?string
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
     * Sanitize filename for ZIP
     */
    private function sanitizeFileName(string $filename): string
    {
        // Remove or replace invalid characters
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        // Remove leading/trailing underscores
        $filename = trim($filename, '_');
        
        return $filename;
    }

    /**
     * Generate ZIP filename based on filters
     */
    private function generateZipFileName(array $filters): string
    {
        $parts = ['file_documents'];
        
        if ($filters['date_from'] && $filters['date_to']) {
            $parts[] = $filters['date_from']->format('Y-m-d') . '_to_' . $filters['date_to']->format('Y-m-d');
        } elseif ($filters['date_from']) {
            $parts[] = 'from_' . $filters['date_from']->format('Y-m-d');
        } elseif ($filters['date_to']) {
            $parts[] = 'until_' . $filters['date_to']->format('Y-m-d');
        }
        
        if (!empty($filters['types']) && count($filters['types']) < 6) {
            $parts[] = implode('_', $filters['types']);
        }
        
        $parts[] = now()->format('Y-m-d_H-i-s');
        
        return implode('_', $parts) . '.zip';
    }

    /**
     * Ensure temp directory exists
     */
    private function ensureTempDirectory(): void
    {
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
    }
}
