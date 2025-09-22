<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\DocumentPathResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class FileUploadController extends Controller
{
    public function uploadDocument(Request $request, File $file)
    {
        try {
            // Validate the request
            $request->validate([
                'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
                'document_type' => 'required|string',
                'directory' => 'required|string',
            ]);

            $uploadedFile = $request->file('file');
            $documentType = $request->input('document_type');
            $directory = $request->input('directory');

            // Generate unique filename
            $extension = $uploadedFile->getClientOriginalExtension();
            $filename = uniqid() . '_' . time() . '.' . $extension;
            
            // Store file
            $filePath = $directory . '/' . $filename;
            Storage::disk('public')->put($filePath, file_get_contents($uploadedFile->getRealPath()));

            // Update the appropriate model based on document type
            $this->updateDocumentPath($file, $filePath, $documentType);

            Log::info('Document uploaded successfully', [
                'file_id' => $file->id,
                'document_type' => $documentType,
                'file_path' => $filePath,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'file_path' => $filePath,
                'filename' => $filename
            ]);

        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function updateDocumentPath(File $file, string $filePath, string $documentType): void
    {
        switch ($documentType) {
            case 'gop':
                $gop = $file->gops()->latest()->first();
                if ($gop) {
                    $gop->update(['document_path' => $filePath]);
                } else {
                    $file->gops()->create([
                        'type' => 'In',
                        'amount' => 0,
                        'date' => now(),
                        'status' => 'Not Sent',
                        'document_path' => $filePath,
                    ]);
                }
                break;

            case 'medical_report':
                $medicalReport = $file->medicalReports()->latest()->first();
                if ($medicalReport) {
                    $medicalReport->update(['document_path' => $filePath]);
                } else {
                    $file->medicalReports()->create([
                        'date' => now(),
                        'status' => 'Received',
                        'document_path' => $filePath,
                    ]);
                }
                break;

            case 'prescription':
                $prescription = $file->prescriptions()->latest()->first();
                if ($prescription) {
                    $prescription->update(['document_path' => $filePath]);
                } else {
                    $file->prescriptions()->create([
                        'name' => 'Uploaded Prescription',
                        'serial' => 'UPL-' . now()->format('YmdHis'),
                        'date' => now(),
                        'document_path' => $filePath,
                    ]);
                }
                break;

            case 'bill':
                $bill = $file->bills()->latest()->first();
                if ($bill) {
                    $bill->update(['bill_document_path' => $filePath]);
                } else {
                    $file->bills()->create([
                        'name' => 'Uploaded Bill',
                        'due_date' => now()->addDays(14),
                        'total_amount' => 0,
                        'discount' => 0,
                        'status' => 'Unpaid',
                        'bill_document_path' => $filePath,
                    ]);
                }
                break;

            case 'invoice':
                $invoice = $file->invoices()->latest()->first();
                if ($invoice) {
                    $invoice->update(['invoice_document_path' => $filePath]);
                } else {
                    $invoice = $file->invoices()->create([
                        'name' => 'Uploaded Invoice',
                        'due_date' => now()->addDays(30),
                        'total_amount' => 0,
                        'discount' => 0,
                        'status' => 'Draft',
                        'invoice_document_path' => $filePath,
                    ]);
                }
                break;

            case 'transaction_in':
                $this->updateTransactionDocumentPath($file, $filePath, 'in');
                break;

            case 'transaction_out':
                $this->updateTransactionDocumentPath($file, $filePath, 'out');
                break;

            default:
                throw new \Exception("Unknown document type: {$documentType}");
        }
    }

    private function updateTransactionDocumentPath(File $file, string $filePath, string $type): void
    {
        $transaction = \App\Models\Transaction::where('related_type', 'File')
            ->where('related_id', $file->id)
            ->where('type', ucfirst($type))
            ->latest()
            ->first();
            
        if ($transaction) {
            $transaction->update(['attachment_path' => $filePath]);
        } else {
            \App\Models\Transaction::create([
                'name' => "Uploaded Transaction ({$type})",
                'amount' => 0,
                'date' => now(),
                'type' => ucfirst($type),
                'related_type' => 'File',
                'related_id' => $file->id,
                'attachment_path' => $filePath,
            ]);
        }
    }
}
