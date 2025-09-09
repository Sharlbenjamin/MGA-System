<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\Log;

class GoogleDriveFileDownloader
{
    private Drive $service;
    private const MAX_RETRIES = 2;
    private const RETRY_SLEEP_SECONDS = 1;

    /**
     * Allowed content types for download
     */
    private const ALLOWED_CONTENT_TYPES = [
        'pdf' => ['application/pdf'],
        'image' => [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/tiff',
            'image/svg+xml'
        ]
    ];

    /**
     * Rejected content types
     */
    private const REJECTED_CONTENT_TYPES = [
        'text/html',
        'text/plain',
        'text/css',
        'text/javascript',
        'application/javascript',
        'application/json',
        'application/xml',
        'text/xml'
    ];

    public function __construct()
    {
        try {
            $client = new Client();
            $client->setAuthConfig(storage_path('app/google-drive/laraveldriveintegration-af9e6ab2e69d.json'));
            $client->addScope(Drive::DRIVE_READONLY);
            $this->service = new Drive($client);
        } catch (\Exception $e) {
            Log::error('Google Drive File Downloader initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Download a file from Google Drive by file ID
     *
     * @param string $fileId
     * @return array{ok: bool, filename?: string, contents?: string, extension?: string, contentType?: string, error?: string}
     */
    public function downloadByFileId(string $fileId): array
    {
        $attempt = 0;
        
        while ($attempt <= self::MAX_RETRIES) {
            try {
                // Get file metadata first
                $fileMetadata = $this->service->files->get($fileId, [
                    'fields' => 'id,name,mimeType,size'
                ]);

                // Validate content type
                $contentTypeValidation = $this->validateContentType($fileMetadata->getMimeType());
                if (!$contentTypeValidation['valid']) {
                    return [
                        'ok' => false,
                        'error' => $contentTypeValidation['error']
                    ];
                }

                // Download file content
                $response = $this->service->files->get($fileId, [
                    'alt' => 'media'
                ]);

                $contents = $response->getBody()->getContents();
                $filename = $fileMetadata->getName();
                $contentType = $fileMetadata->getMimeType();
                $extension = $this->inferExtension($contentType, $filename);

                return [
                    'ok' => true,
                    'filename' => $filename,
                    'contents' => $contents,
                    'extension' => $extension,
                    'contentType' => $contentType
                ];

            } catch (\Google\Service\Exception $e) {
                $attempt++;
                $errorMessage = $this->parseGoogleServiceException($e);
                
                if ($attempt > self::MAX_RETRIES) {
                    Log::error('Google Drive download failed after retries', [
                        'fileId' => $fileId,
                        'attempts' => $attempt,
                        'error' => $errorMessage
                    ]);
                    
                    return [
                        'ok' => false,
                        'error' => "Failed to download file after {$attempt} attempts: {$errorMessage}"
                    ];
                }

                // Sleep before retry
                sleep(self::RETRY_SLEEP_SECONDS);
                Log::warning('Google Drive download attempt failed, retrying', [
                    'fileId' => $fileId,
                    'attempt' => $attempt,
                    'error' => $errorMessage
                ]);

            } catch (\Exception $e) {
                Log::error('Unexpected error during Google Drive download', [
                    'fileId' => $fileId,
                    'error' => $e->getMessage()
                ]);
                
                return [
                    'ok' => false,
                    'error' => 'Unexpected error: ' . $e->getMessage()
                ];
            }
        }

        return [
            'ok' => false,
            'error' => 'Maximum retry attempts exceeded'
        ];
    }

    /**
     * Validate content type against allowed types
     *
     * @param string $contentType
     * @return array{valid: bool, error?: string}
     */
    private function validateContentType(string $contentType): array
    {
        // Check if content type is explicitly rejected
        if (in_array($contentType, self::REJECTED_CONTENT_TYPES)) {
            return [
                'valid' => false,
                'error' => "Content type '{$contentType}' is not allowed for download"
            ];
        }

        // Check if content type is in allowed types
        foreach (self::ALLOWED_CONTENT_TYPES as $category => $types) {
            if (in_array($contentType, $types)) {
                return ['valid' => true];
            }
        }

        // Check for wildcard matches (pdf/*, image/*)
        if (str_starts_with($contentType, 'application/pdf') || 
            str_starts_with($contentType, 'image/')) {
            return ['valid' => true];
        }

        return [
            'valid' => false,
            'error' => "Content type '{$contentType}' is not supported. Only PDF and image files are allowed."
        ];
    }

    /**
     * Infer file extension from content type and filename
     *
     * @param string $contentType
     * @param string $filename
     * @return string
     */
    private function inferExtension(string $contentType, string $filename): string
    {
        // First try to get extension from filename
        $filenameExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($filenameExtension) {
            return $filenameExtension;
        }

        // Map content types to extensions
        $contentTypeToExtension = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/svg+xml' => 'svg'
        ];

        return $contentTypeToExtension[$contentType] ?? 'bin';
    }

    /**
     * Parse Google Service Exception to get meaningful error message
     *
     * @param \Google\Service\Exception $e
     * @return string
     */
    private function parseGoogleServiceException(\Google\Service\Exception $e): string
    {
        $errors = $e->getErrors();
        
        if (empty($errors)) {
            return $e->getMessage();
        }

        $errorMessages = [];
        foreach ($errors as $error) {
            $message = $error['message'] ?? 'Unknown error';
            $reason = $error['reason'] ?? '';
            
            if ($reason) {
                $errorMessages[] = "{$message} (reason: {$reason})";
            } else {
                $errorMessages[] = $message;
            }
        }

        return implode('; ', $errorMessages);
    }

    /**
     * Get file metadata without downloading content
     *
     * @param string $fileId
     * @return array{ok: bool, metadata?: array, error?: string}
     */
    public function getFileMetadata(string $fileId): array
    {
        try {
            $fileMetadata = $this->service->files->get($fileId, [
                'fields' => 'id,name,mimeType,size,createdTime,modifiedTime,webViewLink'
            ]);

            return [
                'ok' => true,
                'metadata' => [
                    'id' => $fileMetadata->getId(),
                    'name' => $fileMetadata->getName(),
                    'mimeType' => $fileMetadata->getMimeType(),
                    'size' => $fileMetadata->getSize(),
                    'createdTime' => $fileMetadata->getCreatedTime(),
                    'modifiedTime' => $fileMetadata->getModifiedTime(),
                    'webViewLink' => $fileMetadata->getWebViewLink()
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get file metadata', [
                'fileId' => $fileId,
                'error' => $e->getMessage()
            ]);

            return [
                'ok' => false,
                'error' => 'Failed to get file metadata: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if a file is downloadable (valid content type)
     *
     * @param string $fileId
     * @return array{downloadable: bool, reason?: string}
     */
    public function isDownloadable(string $fileId): array
    {
        $metadata = $this->getFileMetadata($fileId);
        
        if (!$metadata['ok']) {
            return [
                'downloadable' => false,
                'reason' => $metadata['error']
            ];
        }

        $contentTypeValidation = $this->validateContentType($metadata['metadata']['mimeType']);
        
        return [
            'downloadable' => $contentTypeValidation['valid'],
            'reason' => $contentTypeValidation['error'] ?? null
        ];
    }

    /**
     * Get allowed content types
     *
     * @return array
     */
    public static function getAllowedContentTypes(): array
    {
        return self::ALLOWED_CONTENT_TYPES;
    }

    /**
     * Get rejected content types
     *
     * @return array
     */
    public static function getRejectedContentTypes(): array
    {
        return self::REJECTED_CONTENT_TYPES;
    }
}
