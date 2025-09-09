# Signed Document URLs Usage Guide

## Overview

The signed document URL system provides secure, time-limited access to document files without requiring authentication. This is perfect for Filament table actions, email links, and external integrations.

## Routes

### Document Serving
```
GET /docs/{type}/{id}
```
- **Middleware**: `signed` (requires valid signature)
- **Purpose**: Serves the actual document file
- **Response**: File download/stream

### Document Metadata
```
GET /docs/{type}/{id}/metadata
```
- **Middleware**: `signed` (requires valid signature)
- **Purpose**: Returns document metadata (size, type, etc.)
- **Response**: JSON with file information

## Supported Document Types

| Type | Model | Path Field | Description |
|------|-------|------------|-------------|
| `invoice` | Invoice | `invoice_document_path` | Invoice documents |
| `bill` | Bill | `bill_document_path` | Bill documents |
| `gop` | Gop | `document_path` | GOP documents |
| `medical_report` | MedicalReport | `document_path` | Medical report documents |
| `prescription` | Prescription | `document_path` | Prescription documents |
| `transaction` | Transaction | `attachment_path` | Transaction attachments |

## Model Helper Methods

All document models now include these helper methods:

### `getDocumentSignedUrl(int $expirationMinutes = 60): ?string`
Generates a signed URL for the document file.

### `getDocumentMetadataSignedUrl(int $expirationMinutes = 60): ?string`
Generates a signed URL for document metadata.

## Usage Examples

### Basic Usage
```php
// Get a signed URL for an invoice document (expires in 60 minutes)
$invoice = Invoice::find(1);
$signedUrl = $invoice->getDocumentSignedUrl();

// Get a signed URL with custom expiration (expires in 2 hours)
$signedUrl = $invoice->getDocumentSignedUrl(120);

// Get metadata URL
$metadataUrl = $invoice->getDocumentMetadataSignedUrl();
```

### Filament Table Actions
```php
use Filament\Tables\Actions\Action;

// In your Filament resource
Action::make('view_document')
    ->label('View Document')
    ->icon('heroicon-o-eye')
    ->url(fn ($record) => $record->getDocumentSignedUrl())
    ->openUrlInNewTab()
    ->visible(fn ($record) => $record->hasLocalDocument())
```

### Filament Infolist Actions
```php
use Filament\Infolists\Components\Actions\Action as InfolistAction;

// In your infolist
InfolistAction::make('download_document')
    ->label('Download Document')
    ->icon('heroicon-o-arrow-down-tray')
    ->url(fn ($record) => $record->getDocumentSignedUrl())
    ->openUrlInNewTab()
    ->visible(fn ($record) => $record->hasLocalDocument())
```

### Email Integration
```php
// In your mailable
public function build()
{
    $invoice = $this->invoice;
    
    return $this->view('emails.invoice')
        ->with([
            'invoice' => $invoice,
            'documentUrl' => $invoice->getDocumentSignedUrl(1440), // 24 hours
        ]);
}
```

### Blade Templates
```blade
@if($invoice->hasLocalDocument())
    <a href="{{ $invoice->getDocumentSignedUrl() }}" 
       target="_blank" 
       class="btn btn-primary">
        View Invoice Document
    </a>
@endif
```

## Security Features

### Signed URLs
- **Cryptographic Signature**: URLs are signed with Laravel's app key
- **Time Limited**: URLs expire after specified time (default: 60 minutes)
- **Tamper Proof**: Any modification to the URL invalidates the signature
- **No Authentication Required**: Access is granted based on signature validity

### Access Logging
All document access is logged with:
- Document type and ID
- IP address
- User agent
- Timestamp
- Success/failure status

### File Validation
- **Existence Check**: Verifies file exists on disk before serving
- **Path Validation**: Ensures file path is within allowed directories
- **Type Validation**: Only serves files from configured document paths

## Error Handling

### 404 Not Found
- Document doesn't exist in database
- File doesn't exist on disk
- Invalid document type

### 403 Forbidden
- Invalid or expired signature
- Tampered URL

### 500 Internal Server Error
- File system errors
- Unexpected exceptions

## Performance Considerations

### Caching
- Signed URLs can be cached for their lifetime
- File metadata is retrieved on each request (lightweight)

### File Serving
- Uses Laravel's `Storage::disk('public')->response()` for efficient streaming
- Supports range requests for large files
- Proper MIME type detection

### Memory Usage
- Minimal memory footprint
- No file content loaded into memory
- Direct file streaming

## Configuration

### Default Expiration
```php
// Default: 60 minutes
$url = $model->getDocumentSignedUrl();

// Custom: 2 hours
$url = $model->getDocumentSignedUrl(120);

// Custom: 24 hours
$url = $model->getDocumentSignedUrl(1440);
```

### Storage Configuration
Documents are served from the `public` disk as configured in `config/filesystems.php`:

```php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
],
```

## Monitoring and Logging

### Access Logs
```php
// Successful access
Log::info('Document served via signed URL', [
    'type' => 'invoice',
    'id' => 123,
    'path' => 'files/MGA001/invoices/invoice.pdf',
    'ip' => '192.168.1.1',
    'user_agent' => 'Mozilla/5.0...'
]);

// Failed access
Log::warning('Document not found', [
    'type' => 'invoice',
    'id' => 123,
    'ip' => '192.168.1.1'
]);
```

### Error Tracking
- All errors are logged with full context
- Failed signature validations are tracked
- File system errors are monitored

## Best Practices

### URL Generation
- Generate URLs close to when they're needed
- Use appropriate expiration times for your use case
- Don't cache URLs longer than their expiration time

### Security
- Use HTTPS in production
- Monitor access logs for suspicious activity
- Regularly rotate your app key

### Performance
- Use appropriate expiration times to balance security and usability
- Consider CDN integration for high-traffic scenarios
- Monitor file system performance

## Integration Examples

### Filament Resources
```php
// In your Filament resource table
Tables\Actions\Action::make('download')
    ->label('Download')
    ->icon('heroicon-o-arrow-down-tray')
    ->url(fn ($record) => $record->getDocumentSignedUrl())
    ->openUrlInNewTab()
    ->visible(fn ($record) => $record->hasLocalDocument())
```

### API Endpoints
```php
// In your API controller
public function getDocumentUrl(Request $request, $id)
{
    $invoice = Invoice::findOrFail($id);
    
    return response()->json([
        'document_url' => $invoice->getDocumentSignedUrl(),
        'expires_at' => now()->addMinutes(60)
    ]);
}
```

### Queue Jobs
```php
// In your job
public function handle()
{
    $invoice = $this->invoice;
    $signedUrl = $invoice->getDocumentSignedUrl(1440); // 24 hours
    
    // Send email with signed URL
    Mail::to($invoice->patient->email)->send(new InvoiceMailable($invoice, $signedUrl));
}
```

This system provides a secure, efficient way to share document access without compromising security or requiring complex authentication flows.
