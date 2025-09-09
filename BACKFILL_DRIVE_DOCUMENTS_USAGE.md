# Backfill Drive Documents Usage Guide

## Overview

The backfill system allows you to download Google Drive documents and store them locally using the `DocumentPathResolver` service. This is useful for migrating from Google Drive-only storage to hybrid local/cloud storage.

## Components

### 1. BackfillDriveDocument Job
- Downloads documents from Google Drive
- Validates content (PDF/images only)
- Stores files using `DocumentPathResolver`
- Updates model's `*_document_path` field
- Implements retry/backoff mechanism
- Logs failures to database and daily logs

### 2. QueueBackfillDriveDocuments Command
- Queues backfill jobs for specified document types
- Supports date range filtering
- Chunked processing for large datasets
- Dry-run mode for testing
- Comprehensive progress reporting

### 3. BackfillLog Model
- Tracks backfill attempts and failures
- Provides status monitoring
- Enables retry of failed backfills

## Usage

### Basic Command Usage

```bash
# Backfill all document types
php artisan backfill:drive-documents

# Backfill specific types
php artisan backfill:drive-documents --type=invoices --type=bills

# Backfill with date range
php artisan backfill:drive-documents --from=2024-01-01 --to=2024-12-31

# Dry run to see what would be processed
php artisan backfill:drive-documents --dry-run

# Force processing even if local documents exist
php artisan backfill:drive-documents --force
```

### Command Options

| Option | Description | Example |
|--------|-------------|---------|
| `--type` | Document types to process | `--type=invoices --type=bills` |
| `--from` | Start date (YYYY-MM-DD) | `--from=2024-01-01` |
| `--to` | End date (YYYY-MM-DD) | `--to=2024-12-31` |
| `--chunk` | Records per chunk | `--chunk=50` |
| `--dry-run` | Show what would be processed | `--dry-run` |
| `--force` | Process even with local docs | `--force` |

### Supported Document Types

| Type | Model | Local Field | Google Field | Category |
|------|-------|-------------|--------------|----------|
| `invoices` | Invoice | `invoice_document_path` | `invoice_google_link` | `invoices` |
| `bills` | Bill | `bill_document_path` | `bill_google_link` | `bills` |
| `gops` | Gop | `document_path` | `gop_google_drive_link` | `gops` |
| `medical_reports` | MedicalReport | `document_path` | N/A | `medical_reports` |
| `prescriptions` | Prescription | `document_path` | N/A | `prescriptions` |
| `transactions` | Transaction | `attachment_path` | N/A | `transactions` |

## Examples

### Backfill Recent Invoices
```bash
php artisan backfill:drive-documents \
  --type=invoices \
  --from=2024-01-01 \
  --to=2024-12-31 \
  --chunk=100
```

### Test Backfill Process
```bash
php artisan backfill:drive-documents \
  --type=bills \
  --from=2024-06-01 \
  --to=2024-06-30 \
  --dry-run
```

### Force Re-backfill All Documents
```bash
php artisan backfill:drive-documents \
  --force \
  --chunk=50
```

## Job Configuration

### Retry Mechanism
- **Max Attempts**: 3
- **Backoff**: 30s, 1m, 2m
- **Timeout**: 5 minutes per job

### Content Validation
- **File Types**: PDF, JPEG, PNG, GIF, WebP
- **Minimum Size**: 100 bytes
- **Content Signatures**: Validates file headers

### Storage
- **Location**: `storage/app/public/files/{mga_reference}/{category}/`
- **Naming**: Sanitized model names with proper extensions
- **Path Resolution**: Uses `DocumentPathResolver::ensurePathFor()`

## Monitoring and Logging

### Database Logging
The `backfill_logs` table tracks:
- Model class and ID
- Field being updated
- Google Drive link
- Error messages
- Attempt count
- Status (retrying, success, failed)
- Timestamps

### Log Channels
- **Application Log**: Standard Laravel log
- **Daily Log**: `storage/logs/laravel-YYYY-MM-DD.log`
- **Database**: `backfill_logs` table

### Monitoring Queries
```php
// Get failed backfills
$failed = BackfillLog::getByStatus('failed');

// Get retrying backfills
$retrying = BackfillLog::getByStatus('retrying');

// Get failed backfills older than 24 hours
$oldFailures = BackfillLog::getFailedOlderThan(24);

// Get logs for specific model
$logs = BackfillLog::getForModel(Invoice::class, 123);
```

## Error Handling

### Common Errors
1. **Model Not Found**: Record deleted after job queued
2. **File ID Extraction Failed**: Invalid Google Drive URL format
3. **Download Failed**: Network issues, permissions, file deleted
4. **Content Validation Failed**: Invalid file type or corrupted content
5. **Storage Failed**: Disk space, permissions, path issues

### Retry Logic
- **Automatic Retries**: Up to 3 attempts with exponential backoff
- **Manual Retry**: Re-queue failed jobs using log entries
- **Permanent Failure**: Logged after all retries exhausted

## Performance Considerations

### Chunking
- **Default Chunk Size**: 100 records
- **Memory Efficient**: Processes records in batches
- **Queue Distribution**: Jobs distributed across queue workers

### Resource Usage
- **Memory**: Minimal per job (streams file content)
- **Disk I/O**: Direct file operations
- **Network**: Google Drive API calls with retry logic

### Optimization Tips
1. **Adjust Chunk Size**: Smaller chunks for memory-constrained environments
2. **Queue Workers**: Scale workers based on Google Drive API limits
3. **Time Windows**: Process during off-peak hours
4. **Monitoring**: Watch queue length and failure rates

## Security

### Access Control
- **Google Drive**: Uses service account with appropriate scopes
- **File Storage**: Files stored in public disk (configured access)
- **Logging**: Sensitive URLs logged (consider sanitization)

### Data Protection
- **Content Validation**: Prevents malicious file uploads
- **Path Sanitization**: Prevents directory traversal
- **Error Handling**: No sensitive data in error messages

## Troubleshooting

### Common Issues

#### Job Stuck in Queue
```bash
# Check queue status
php artisan queue:work --once

# Restart queue workers
php artisan queue:restart
```

#### High Failure Rate
```bash
# Check recent failures
php artisan tinker
>>> App\Models\BackfillLog::where('status', 'failed')->latest()->take(10)->get()

# Check Google Drive API limits
# Monitor API quota in Google Cloud Console
```

#### Storage Issues
```bash
# Check disk space
df -h

# Check storage permissions
ls -la storage/app/public/

# Test file creation
php artisan tinker
>>> Storage::disk('public')->put('test.txt', 'test')
```

### Debug Mode
```bash
# Enable verbose logging
php artisan backfill:drive-documents --type=invoices --dry-run -v

# Check specific model
php artisan tinker
>>> $invoice = App\Models\Invoice::find(123);
>>> $invoice->invoice_google_link;
>>> $invoice->invoice_document_path;
```

## Integration

### Queue Configuration
```php
// config/queue.php
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 300,
    ],
],
```

### Cron Job
```bash
# Add to crontab for scheduled backfills
0 2 * * * cd /path/to/project && php artisan backfill:drive-documents --type=invoices --from=$(date -d "yesterday" +\%Y-\%m-\%d) --to=$(date -d "yesterday" +\%Y-\%m-\%d)
```

### Monitoring Integration
```php
// Custom monitoring command
php artisan backfill:status

// Check queue health
php artisan queue:monitor
```

## Best Practices

### Planning
1. **Test First**: Always use `--dry-run` before production runs
2. **Start Small**: Begin with recent data and specific types
3. **Monitor Progress**: Watch logs and queue status
4. **Backup**: Ensure Google Drive data is backed up

### Execution
1. **Off-Peak Hours**: Run during low-traffic periods
2. **Resource Monitoring**: Watch memory, disk, and API usage
3. **Error Handling**: Review and address failures promptly
4. **Documentation**: Keep records of what was processed

### Maintenance
1. **Regular Cleanup**: Remove old log entries
2. **Queue Health**: Monitor queue length and worker status
3. **API Limits**: Track Google Drive API usage
4. **Storage Management**: Monitor disk usage and cleanup

This system provides a robust, scalable solution for migrating Google Drive documents to local storage while maintaining data integrity and providing comprehensive monitoring capabilities.
