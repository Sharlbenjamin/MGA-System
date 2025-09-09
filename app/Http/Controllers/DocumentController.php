<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Invoice;
use App\Models\Bill;
use App\Models\Gop;
use App\Models\MedicalReport;
use App\Models\Prescription;
use App\Models\Transaction;

class DocumentController extends Controller
{
    /**
     * Serve document files via signed URLs
     * 
     * @param Request $request
     * @param string $type
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function serve(Request $request, string $type, int $id)
    {
        try {
            // Resolve model and get document path
            $documentPath = $this->resolveDocumentPath($type, $id);
            
            if (!$documentPath) {
                Log::warning('Document not found', [
                    'type' => $type,
                    'id' => $id,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                
                return response()->json(['error' => 'Document not found'], 404);
            }

            // Check if file exists
            if (!Storage::disk('public')->exists($documentPath)) {
                Log::warning('Document file not found on disk', [
                    'type' => $type,
                    'id' => $id,
                    'path' => $documentPath,
                    'ip' => $request->ip()
                ]);
                
                return response()->json(['error' => 'Document file not found'], 404);
            }

            // Log successful access
            Log::info('Document served via signed URL', [
                'type' => $type,
                'id' => $id,
                'path' => $documentPath,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Return file response
            return Storage::disk('public')->response($documentPath);

        } catch (\Exception $e) {
            Log::error('Error serving document', [
                'type' => $type,
                'id' => $id,
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Resolve document path based on type and ID
     * 
     * @param string $type
     * @param int $id
     * @return string|null
     */
    private function resolveDocumentPath(string $type, int $id): ?string
    {
        switch ($type) {
            case 'invoice':
                $model = Invoice::find($id);
                return $model?->invoice_document_path;
                
            case 'bill':
                $model = Bill::find($id);
                return $model?->bill_document_path;
                
            case 'gop':
                $model = Gop::find($id);
                return $model?->document_path;
                
            case 'medical_report':
                $model = MedicalReport::find($id);
                return $model?->document_path;
                
            case 'prescription':
                $model = Prescription::find($id);
                return $model?->document_path;
                
            case 'transaction':
                $model = Transaction::find($id);
                return $model?->attachment_path;
                
            default:
                Log::warning('Unknown document type requested', [
                    'type' => $type,
                    'id' => $id
                ]);
                return null;
        }
    }

    /**
     * Get document metadata without serving the file
     * 
     * @param Request $request
     * @param string $type
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function metadata(Request $request, string $type, int $id)
    {
        try {
            $documentPath = $this->resolveDocumentPath($type, $id);
            
            if (!$documentPath) {
                return response()->json(['error' => 'Document not found'], 404);
            }

            if (!Storage::disk('public')->exists($documentPath)) {
                return response()->json(['error' => 'Document file not found'], 404);
            }

            $fileSize = Storage::disk('public')->size($documentPath);
            $lastModified = Storage::disk('public')->lastModified($documentPath);
            $mimeType = Storage::disk('public')->mimeType($documentPath);

            return response()->json([
                'path' => $documentPath,
                'size' => $fileSize,
                'size_formatted' => $this->formatFileSize($fileSize),
                'last_modified' => $lastModified,
                'last_modified_formatted' => date('Y-m-d H:i:s', $lastModified),
                'mime_type' => $mimeType,
                'filename' => basename($documentPath),
                'extension' => pathinfo($documentPath, PATHINFO_EXTENSION)
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting document metadata', [
                'type' => $type,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Format file size in human readable format
     * 
     * @param int $bytes
     * @return string
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
