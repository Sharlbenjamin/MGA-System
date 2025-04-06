<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;

class GoogleDriveFolderService
{
    private $service;
    private const SHARED_DRIVE_ID = '0AEVbbPnDprotUk9PVA';

    public function __construct()
    {
        try {
            $client = new Client();
            $client->setAuthConfig(storage_path('app/google-drive/laraveldriveintegration-af9e6ab2e69d.json'));
            $client->addScope(Drive::DRIVE);
            $this->service = new Drive($client);
        } catch (\Exception $e) {
            Log::error('Google Drive Service initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function generateGoogleDriveFolder($file)
    {
        try {
            if (empty(self::SHARED_DRIVE_ID)) {
                throw new \Exception('Shared Drive ID not configured');
            }

            $folderName = $file->mga_reference;
            $caseYear = $file->created_at->year;
            $caseMonth = str_pad($file->created_at->month, 2, '0', STR_PAD_LEFT);
            $caseDay = str_pad($file->created_at->day, 2, '0', STR_PAD_LEFT);

            // Check and create year folder directly in shared drive root
            $yearFolderId = $this->findOrCreateFolder($caseYear);

            // Check and create month folder
            $monthFolderId = $this->findOrCreateFolder($caseMonth, $yearFolderId);

            // Check and create day folder
            $dayFolderId = $this->findOrCreateFolder($caseDay, $monthFolderId);

            // Create the case folder
            $caseFolderId = $this->createFolder($folderName, $dayFolderId);

            // Get the full folder URL
            $folderUrl = $this->getFolderUrl($caseFolderId);

            // Save the folder link to the file
            $file->google_drive_link = $folderUrl;
            $file->save();

            return $folderUrl;

        } catch (\Exception $e) {
            Log::error('Failed to create Google Drive folder: ' . $e->getMessage());
            throw $e;
        }
    }

    private function findOrCreateFolder($name, $parentId = null)
    {
        // Build the query
        $query = "mimeType='application/vnd.google-apps.folder' and name='{$name}' and trashed=false";
        if ($parentId) {
            $query .= " and '{$parentId}' in parents";
        }

        // Search for existing folder
        $results = $this->service->files->listFiles([
            'q' => $query,
            'spaces' => 'drive',
            'fields' => 'files(id, name)',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
            'driveId' => self::SHARED_DRIVE_ID,
            'corpora' => 'drive'
        ]);

        // Return existing folder ID if found
        if (count($results->getFiles()) > 0) {
            return $results->getFiles()[0]->getId();
        }

        // Create new folder if not found
        return $this->createFolder($name, $parentId);
    }

    private function createFolder($name, $parentId = null)
    {
        $fileMetadata = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder'
        ]);

        // For the root of shared drive
        if (!$parentId) {
            $fileMetadata->setParents([self::SHARED_DRIVE_ID]);
        } else {
            $fileMetadata->setParents([$parentId]);
        }

        $folder = $this->service->files->create($fileMetadata, [
            'fields' => 'id, webViewLink',
            'supportsAllDrives' => true
        ]);

        return $folder->getId();
    }

    private function getFolderUrl($folderId)
    {
        try {
            $file = $this->service->files->get($folderId, [
                'fields' => 'webViewLink',
                'supportsAllDrives' => true
            ]);
            return $file->getWebViewLink();
        } catch (\Exception $e) {
            // Fallback to traditional URL format if webViewLink is not available
            return "https://drive.google.com/drive/folders/{$folderId}";
        }
    }
}
