<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;

class UploadTransactionToGoogleDrive
{
    private $folderService;

    public function __construct(GoogleDriveFolderService $folderService)
    {
        $this->folderService = $folderService;
    }

    private function checkFolderAccess($service, $folderId)
    {
        try {
            $service->files->get($folderId, [
                'fields' => 'id',
                'supportsAllDrives' => true
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function extractFolderId($driveLink)
    {
        return preg_match('/folders\/([a-zA-Z0-9_-]+)/', $driveLink, $matches)
            ? $matches[1]
            : $driveLink;
    }

    public function uploadTransactionToGoogleDrive($fileContent, $fileName, $transaction)
    {
        try {
            $credentialsPath = storage_path('app/google-drive/laraveldriveintegration-af9e6ab2e69d.json');
            if (!file_exists($credentialsPath)) return false;

            $client = new Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(Drive::DRIVE);
            $service = new Drive($client);

            // For transactions, we need to get the file through the related entity
            $file = null;
            
            if ($transaction->related_type === 'Invoice') {
                $invoice = \App\Models\Invoice::find($transaction->related_id);
                $file = $invoice->file ?? $invoice->patient->files()->first();
            } elseif ($transaction->related_type === 'Bill') {
                $bill = \App\Models\Bill::find($transaction->related_id);
                $file = $bill->file;
            } elseif ($transaction->related_type === 'Client') {
                // For client transactions, we might need to create a general folder
                // or use the first file associated with the client
                $client = \App\Models\Client::find($transaction->related_id);
                $file = $client->patients()->first()?->files()->first();
            }

            if (!$file) {
                Log::error('No file found for transaction', ['transaction_id' => $transaction->id]);
                return false;
            }

            if (empty($file->google_drive_link)) {
                $this->folderService->generateGoogleDriveFolder($file);
                if (empty($file->google_drive_link)) return false;
            }

            $parentFolderId = $this->extractFolderId($file->google_drive_link);
            if (!$this->checkFolderAccess($service, $parentFolderId)) return false;

            $file = $service->files->create(
                new DriveFile([
                    'name' => $fileName,
                    'parents' => [$parentFolderId]
                ]),
                [
                    'data' => $fileContent,
                    'mimeType' => 'application/pdf',
                    'uploadType' => 'multipart',
                    'fields' => 'id, webViewLink',
                    'supportsAllDrives' => true
                ]
            );

            // Since there's no transaction_google_link field, we'll store the link in attachment_path
            // or we could add a comment in the notes field
            $transaction->update(['attachment_path' => $file->webViewLink]);

            return [
                'id' => $file->id,
                'webViewLink' => $file->webViewLink
            ];
        } catch (\Exception $e) {
            Log::error('Transaction upload failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
} 