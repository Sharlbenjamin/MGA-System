# GoogleDriveFileDownloader Usage Examples

## Basic Usage

```php
use App\Services\GoogleDriveFileDownloader;

$downloader = new GoogleDriveFileDownloader();

// Download a file by Google Drive file ID
$result = $downloader->downloadByFileId('1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms');

if ($result['ok']) {
    // File downloaded successfully
    $filename = $result['filename'];        // "document.pdf"
    $contents = $result['contents'];        // Binary file contents
    $extension = $result['extension'];      // "pdf"
    $contentType = $result['contentType'];  // "application/pdf"
    
    // Save to local storage
    file_put_contents(storage_path("app/documents/{$filename}"), $contents);
} else {
    // Handle error
    $error = $result['error'];
    echo "Download failed: {$error}";
}
```

## Supported File Types

### ✅ **Allowed Content Types:**
- **PDF**: `application/pdf`
- **Images**: 
  - `image/jpeg`, `image/jpg`
  - `image/png`
  - `image/gif`
  - `image/webp`
  - `image/bmp`
  - `image/tiff`
  - `image/svg+xml`

### ❌ **Rejected Content Types:**
- `text/html`
- `text/plain`
- `text/css`
- `text/javascript`
- `application/javascript`
- `application/json`
- `application/xml`
- `text/xml`

## Advanced Usage

### Check if File is Downloadable

```php
$fileId = '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms';
$check = $downloader->isDownloadable($fileId);

if ($check['downloadable']) {
    // Proceed with download
    $result = $downloader->downloadByFileId($fileId);
} else {
    echo "Cannot download: {$check['reason']}";
}
```

### Get File Metadata

```php
$metadata = $downloader->getFileMetadata($fileId);

if ($metadata['ok']) {
    $fileInfo = $metadata['metadata'];
    echo "Name: {$fileInfo['name']}";
    echo "Size: {$fileInfo['size']} bytes";
    echo "Type: {$fileInfo['mimeType']}";
    echo "Created: {$fileInfo['createdTime']}";
    echo "Modified: {$fileInfo['modifiedTime']}";
    echo "View Link: {$fileInfo['webViewLink']}";
}
```

### Integration with DocumentPathResolver

```php
use App\Services\DocumentPathResolver;
use App\Services\GoogleDriveFileDownloader;
use App\Models\File;

$file = File::find(1);
$resolver = new DocumentPathResolver();
$downloader = new GoogleDriveFileDownloader();

// Download from Google Drive
$googleDriveFileId = '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms';
$downloadResult = $downloader->downloadByFileId($googleDriveFileId);

if ($downloadResult['ok']) {
    // Get local storage path
    $localPath = $resolver->ensurePathFor($file, 'invoices', $downloadResult['filename']);
    
    // Save to local storage
    Storage::disk('public')->put($localPath, $downloadResult['contents']);
    
    // Update model with local path
    $invoice = $file->invoices()->first();
    $invoice->invoice_document_path = $localPath;
    $invoice->save();
}
```

## Error Handling

```php
try {
    $result = $downloader->downloadByFileId($fileId);
    
    if (!$result['ok']) {
        // Handle specific error cases
        if (str_contains($result['error'], 'not allowed')) {
            // Content type not supported
            throw new \Exception('File type not supported for download');
        } elseif (str_contains($result['error'], 'Failed to download file after')) {
            // Retry limit exceeded
            throw new \Exception('Download failed after multiple attempts');
        } else {
            // Other errors
            throw new \Exception($result['error']);
        }
    }
    
} catch (\Exception $e) {
    Log::error('Google Drive download failed', [
        'fileId' => $fileId,
        'error' => $e->getMessage()
    ]);
}
```

## Configuration

The service uses the same Google API credentials as other Google services in the project:

- **Credentials File**: `storage/app/google-drive/laraveldriveintegration-af9e6ab2e69d.json`
- **Scope**: `Drive::DRIVE_READONLY`
- **Retry Logic**: Maximum 2 retries with 1-second sleep between attempts

## Return Format

All methods return arrays with the following structure:

### Success Response:
```php
[
    'ok' => true,
    'filename' => 'document.pdf',
    'contents' => 'binary file contents...',
    'extension' => 'pdf',
    'contentType' => 'application/pdf'
]
```

### Error Response:
```php
[
    'ok' => false,
    'error' => 'Error message describing what went wrong'
]
```

### Metadata Response:
```php
[
    'ok' => true,
    'metadata' => [
        'id' => 'file-id',
        'name' => 'filename.pdf',
        'mimeType' => 'application/pdf',
        'size' => 1024,
        'createdTime' => '2023-01-01T00:00:00.000Z',
        'modifiedTime' => '2023-01-02T00:00:00.000Z',
        'webViewLink' => 'https://drive.google.com/file/d/file-id/view'
    ]
]
```

## Utility Methods

```php
// Get all allowed content types
$allowedTypes = GoogleDriveFileDownloader::getAllowedContentTypes();

// Get all rejected content types  
$rejectedTypes = GoogleDriveFileDownloader::getRejectedContentTypes();

// Check if content type is allowed
$isAllowed = in_array('application/pdf', $allowedTypes['pdf']);
```
