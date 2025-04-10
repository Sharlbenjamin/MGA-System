<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;

class UploadInvoiceToGoogleDrive
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

    public function uploadInvoiceToGoogleDrive($fileContent, $fileName, $invoice)
    {
        try {
            $credentialsPath = storage_path('app/google-drive/laraveldriveintegration-af9e6ab2e69d.json');
            if (!file_exists($credentialsPath)) return false;

            $client = new Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(Drive::DRIVE);
            $service = new Drive($client);

            if (empty($invoice->file->google_drive_link)) {
                $this->folderService->generateGoogleDriveFolder($invoice->file);
                if (empty($invoice->file->google_drive_link)) return false;
            }

            $parentFolderId = $this->extractFolderId($invoice->file->google_drive_link);
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

            $invoice->update(['invoice_google_link' => $file->webViewLink]);

            return [
                'id' => $file->id,
                'webViewLink' => $file->webViewLink
            ];
        } catch (\Exception) {
            return false;
        }
    }
}