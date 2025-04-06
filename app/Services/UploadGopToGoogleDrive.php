<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;

class UploadGopToGoogleDrive
{
    private $folderService;

    public function __construct(GoogleDriveFolderService $folderService)
    {
        $this->folderService = $folderService;
    }

    private function checkFolderAccess($service, $folderId)
    {
        try {
            // Try to get folder metadata to verify access
            $file = $service->files->get($folderId, [
                'fields' => 'id, name',
                'supportsAllDrives' => true
            ]);
            Log::info('Folder access verified', ['folder' => $file->getName()]);
            return true;
        } catch (\Exception $e) {
            Log::error('Folder access failed', [
                'folderId' => $folderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function createGopFolder($service, $parentFolderId)
    {
        try {
            // First verify folder access
            if (!$this->checkFolderAccess($service, $parentFolderId)) {
                Log::error('Cannot access parent folder');
                return false;
            }

            $folderMetadata = new DriveFile([
                'name' => 'GOP',
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentFolderId]
            ]);

            $folder = $service->files->create($folderMetadata, [
                'fields' => 'id',
                'supportsAllDrives' => true
            ]);

            Log::info('GOP folder created', ['folderId' => $folder->id]);
            return $folder->id;
        } catch (\Exception $e) {
            Log::error('Failed to create GOP folder', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function extractFolderId($driveLink)
    {
        if (preg_match('/folders\/([a-zA-Z0-9_-]+)/', $driveLink, $matches)) {
            return $matches[1];
        }
        return $driveLink;
    }

    public function uploadGopToGoogleDrive($fileContent, $fileName, $gop)
    {
        try {
            $credentialsPath = storage_path('app/google-drive/laraveldriveintegration-af9e6ab2e69d.json');
            if (!file_exists($credentialsPath)) {
                return false;
            }

            $client = new Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(Drive::DRIVE);
            $service = new Drive($client);

            if (empty($gop->file->google_drive_link)) {
                $this->folderService->generateGoogleDriveFolder($gop->file);
                if (empty($gop->file->google_drive_link)) {
                    return false;
                }
            }

            $parentFolderId = $this->extractFolderId($gop->file->google_drive_link);

            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => [$parentFolderId]
            ]);

            $file = $service->files->create($fileMetadata, [
                'data' => $fileContent,
                'mimeType' => 'application/pdf',
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink',
                'supportsAllDrives' => true
            ]);

            $gop->gop_google_drive_link = $file->webViewLink;
            $gop->save();

            return $file->id;
        } catch (\Exception $e) {
            Log::error('Google Drive Upload Error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
