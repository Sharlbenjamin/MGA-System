# Document Access Debugging Steps

## Current Setup

1. **Route Definition** (routes/web.php:145-148)
   - Route uses `signed` middleware
   - Route is OUTSIDE authentication middleware groups
   - Route path: `/docs/{type}/{id}`

2. **Model Methods** (app/Models/*.php)
   - All models use `URL::temporarySignedRoute()` 
   - Method: `getDocumentSignedUrl()`
   - Checks: `hasLocalDocument()` returns true if `document_path` exists

3. **DocumentController** (app/Http/Controllers/DocumentController.php)
   - Serves files from `Storage::disk('public')`
   - Resolves document path from model's `document_path` field
   - No authentication required (signed URLs handle security)

## Verification Steps

### Step 1: Check if document_path is set
```php
// In tinker or check database
$gop = App\Models\Gop::find(1);
dd($gop->document_path); // Should return a path like "files/MGA-123/gops/filename.pdf"
```

### Step 2: Verify signed URL generation
```php
$gop = App\Models\Gop::find(1);
$url = $gop->getDocumentSignedUrl();
dd($url); // Should return a URL with signature parameter
```

### Step 3: Check if file exists on disk
```php
$gop = App\Models\Gop::find(1);
$exists = Storage::disk('public')->exists($gop->document_path);
dd($exists); // Should return true
```

### Step 4: Test the route directly
```bash
# Generate a signed URL
php artisan tinker
>>> $gop = App\Models\Gop::find(1);
>>> $url = $gop->getDocumentSignedUrl();
>>> echo $url;

# Then test in browser or curl
curl -v "GENERATED_URL_HERE"
```

### Step 5: Check Laravel logs
```bash
tail -f storage/logs/laravel.log
# Then try accessing the document
# Look for errors related to:
# - "Invalid signature"
# - "Document not found"
# - "Document file not found on disk"
```

## Common Issues

### Issue 1: document_path is NULL
**Symptom**: Buttons don't show or URL is null
**Solution**: Ensure documents are uploaded and `document_path` is saved

### Issue 2: File doesn't exist on disk
**Symptom**: 404 error in logs
**Solution**: Check if file was actually saved to storage/public/files/...

### Issue 3: Invalid signature
**Symptom**: 403 Forbidden error
**Possible causes**:
- APP_KEY changed after URL was generated
- URL was modified/corrupted
- Timezone mismatch
- APP_URL not set correctly

### Issue 4: PasswordProtect middleware interfering
**Symptom**: Redirect to password form
**Solution**: Signed routes should be outside PasswordProtect middleware (they are)

## Quick Fixes

1. **Clear route cache**: `php artisan route:clear`
2. **Clear config cache**: `php artisan config:clear`
3. **Check APP_KEY**: Ensure it's set and hasn't changed
4. **Check APP_URL**: Should match your domain
5. **Verify file permissions**: Storage should be writable

