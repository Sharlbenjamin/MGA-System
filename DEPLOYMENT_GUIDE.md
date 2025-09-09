# Deployment Guide

## Overview

This guide covers the deployment process for the MGA System with all the new document management features, including migrations, storage setup, and testing procedures.

## Pre-Deployment Checklist

### 1. Environment Configuration
- [ ] Ensure `APP_URL` is set correctly in `.env`
- [ ] Verify `FILESYSTEM_DISK` is set to `local` (default)
- [ ] Check Google Drive credentials are in place
- [ ] Confirm queue configuration is set up
- [ ] Verify cron job is configured for Laravel scheduler

### 2. Server Requirements
- [ ] PHP 8.1+ with required extensions
- [ ] Composer dependencies installed
- [ ] Node.js and NPM for asset compilation
- [ ] Sufficient disk space for document storage
- [ ] Proper file permissions for storage directories

## Deployment Steps

### 1. Code Deployment
```bash
# Pull latest code
git pull origin main

# Install/update dependencies
composer install --optimize-autoloader --no-dev

# Compile assets
npm ci
npm run build
```

### 2. Database Migrations
```bash
# Run new migrations
php artisan migrate

# Verify migration status
php artisan migrate:status
```

**New Migrations Included:**
- `2025_09_09_130811_add_document_path_columns_to_tables.php` - Adds document path columns
- `2025_01_15_150000_create_backfill_logs_table.php` - Creates backfill logging table

### 3. Storage Setup
```bash
# Create storage link for public files
php artisan storage:link

# Verify storage link
ls -la public/storage

# Set proper permissions
chmod -R 775 storage/
chmod -R 775 public/storage/
```

### 4. Cache Optimization
```bash
# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 5. Queue Setup
```bash
# Restart queue workers
php artisan queue:restart

# Start queue workers (if not using supervisor)
php artisan queue:work --daemon
```

### 6. Scheduler Setup
```bash
# Test scheduler
php artisan schedule:list

# Verify cron job is set up
crontab -l | grep "php artisan schedule:run"
```

**Required Cron Entry:**
```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

## Post-Deployment Testing

### 1. File Upload Testing
```bash
# Test document upload functionality
# Navigate to FileResource View page
# Try uploading documents in each category:
# - GOP Documents
# - Medical Reports
# - Prescriptions
# - Bills
# - Invoices
# - Transactions
```

### 2. Export ZIP Testing
```bash
# Test taxes export
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/taxes/export/zip?year=2024&quarter=1"

# Test file document export
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/files/export/zip?date_from=2024-01-01&date_to=2024-12-31"
```

### 3. Backfill Command Testing
```bash
# Test backfill command (dry run first)
php artisan backfill:drive-documents --dry-run --type=invoices

# Test with small date range
php artisan backfill:drive-documents \
  --type=invoices \
  --from=2024-01-01 \
  --to=2024-01-31 \
  --chunk=10

# Monitor queue
php artisan queue:work --once
```

### 4. Signed URL Testing
```bash
# Test signed URL generation
php artisan tinker
>>> $invoice = App\Models\Invoice::first();
>>> $invoice->getDocumentSignedUrl();
>>> exit

# Test document serving
# Use generated signed URL in browser
```

### 5. Cleanup Command Testing
```bash
# Test cleanup command (dry run first)
php artisan clean:temp-zips --dry-run

# Test with force option
php artisan clean:temp-zips --force --dry-run

# Test scheduled execution
php artisan schedule:run
```

## Verification Commands

### 1. Check Migration Status
```bash
php artisan migrate:status
```

### 2. Verify Storage Link
```bash
ls -la public/storage
# Should show: public/storage -> /path/to/storage/app/public
```

### 3. Check File Permissions
```bash
ls -la storage/app/public/
ls -la public/storage/
```

### 4. Verify Queue Status
```bash
php artisan queue:work --once
php artisan queue:failed
```

### 5. Check Scheduler
```bash
php artisan schedule:list
```

## Troubleshooting

### Common Issues

#### 1. Storage Link Issues
```bash
# Remove existing link
rm public/storage

# Recreate link
php artisan storage:link

# Check permissions
chmod -R 775 storage/
chmod -R 775 public/storage/
```

#### 2. Migration Failures
```bash
# Check migration status
php artisan migrate:status

# Rollback if needed
php artisan migrate:rollback --step=1

# Re-run migration
php artisan migrate
```

#### 3. Queue Issues
```bash
# Clear failed jobs
php artisan queue:flush

# Restart workers
php artisan queue:restart

# Check queue configuration
php artisan config:show queue
```

#### 4. Permission Issues
```bash
# Fix ownership
sudo chown -R www-data:www-data storage/
sudo chown -R www-data:www-data public/storage/

# Fix permissions
sudo chmod -R 775 storage/
sudo chmod -R 775 public/storage/
```

### 5. Google Drive API Issues
```bash
# Check credentials
ls -la storage/app/google-drive/

# Test API connection
php artisan tinker
>>> app(App\Services\GoogleDriveFileDownloader::class);
>>> exit
```

## Monitoring

### 1. Log Monitoring
```bash
# Monitor application logs
tail -f storage/logs/laravel.log

# Monitor daily logs
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

### 2. Queue Monitoring
```bash
# Check queue length
php artisan queue:monitor

# Monitor failed jobs
php artisan queue:failed
```

### 3. Storage Monitoring
```bash
# Check disk usage
df -h

# Check storage directory size
du -sh storage/app/public/
```

### 4. Backfill Monitoring
```bash
# Check backfill logs
php artisan tinker
>>> App\Models\BackfillLog::where('status', 'failed')->count();
>>> App\Models\BackfillLog::where('status', 'success')->count();
>>> exit
```

## Performance Optimization

### 1. Queue Optimization
```bash
# Use supervisor for queue workers
# Configure multiple workers for parallel processing
# Set appropriate memory limits
```

### 2. Storage Optimization
```bash
# Regular cleanup of temp files
# Monitor disk usage
# Consider CDN for public files
```

### 3. Database Optimization
```bash
# Index optimization
# Regular maintenance
# Monitor query performance
```

## Security Considerations

### 1. File Access
- Signed URLs provide secure access
- Files stored in public disk with proper permissions
- No direct file system access

### 2. API Security
- Google Drive API uses service account
- Proper scoping and permissions
- Rate limiting considerations

### 3. Log Security
- Sensitive data in logs
- Regular log rotation
- Secure log storage

## Rollback Plan

### 1. Code Rollback
```bash
# Revert to previous commit
git reset --hard HEAD~1

# Reinstall dependencies
composer install
```

### 2. Database Rollback
```bash
# Rollback migrations
php artisan migrate:rollback --step=2
```

### 3. Storage Rollback
```bash
# Remove storage link
rm public/storage

# Restore previous configuration
```

## Success Criteria

### 1. Functional Tests
- [ ] File uploads work in all categories
- [ ] Export ZIP functionality works
- [ ] Backfill command processes documents
- [ ] Signed URLs provide secure access
- [ ] Cleanup command removes temp files

### 2. Performance Tests
- [ ] File uploads complete within reasonable time
- [ ] Export operations handle large datasets
- [ ] Queue processing maintains throughput
- [ ] Storage operations are efficient

### 3. Security Tests
- [ ] Signed URLs expire correctly
- [ ] File access is properly restricted
- [ ] No sensitive data in logs
- [ ] API credentials are secure

## Post-Deployment Tasks

### 1. Documentation
- [ ] Update system documentation
- [ ] Document any custom configurations
- [ ] Record deployment notes

### 2. Monitoring Setup
- [ ] Configure log monitoring
- [ ] Set up queue monitoring
- [ ] Monitor storage usage
- [ ] Track backfill progress

### 3. User Training
- [ ] Train users on new document features
- [ ] Provide usage documentation
- [ ] Set up support procedures

This deployment guide ensures a smooth transition to the new document management system with proper testing and verification at each step.
