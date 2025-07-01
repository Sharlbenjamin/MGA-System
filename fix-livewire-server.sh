#!/bin/bash

echo "🔧 Applying Livewire tmpfile() patch to server..."

# Navigate to the application directory
cd /home/1417710.cloudwaysapps.com/fdcpgwbqxd/public_html

# Create backup of the original file
cp vendor/livewire/livewire/src/Features/SupportFileUploads/TemporaryUploadedFile.php vendor/livewire/livewire/src/Features/SupportFileUploads/TemporaryUploadedFile.php.backup.$(date +%Y%m%d_%H%M%S)

echo "✅ Backup created"

# Create the livewire-temp directory
mkdir -p storage/app/livewire-temp
chmod 755 storage/app/livewire-temp

echo "✅ Created livewire-temp directory"

# Apply the patch using sed - replace the first tmpfile() call (around line 23)
sed -i 's/\$tempFile = tmpfile();/\
        \/\/ Use a temporary file in the storage directory instead of tmpfile()\
        \$tempPath = storage_path('\''app\/livewire-temp\/'\'' . uniqid() . '\''.tmp'\'');\
        \$tempDir = dirname(\$tempPath);\
        \
        if (!is_dir(\$tempDir)) {\
            mkdir(\$tempDir, 0755, true);\
        }\
        \
        file_put_contents(\$tempPath, '\'''\'');\
        \$tempFile = fopen(\$tempPath, '\''r+'\'');/' vendor/livewire/livewire/src/Features/SupportFileUploads/TemporaryUploadedFile.php

echo "✅ Applied first patch"

# Apply the patch for the second tmpfile() call (around line 109)
sed -i '/dimensions()/,/}/s/\$tempFile = tmpfile();/\
        \$tempPath = storage_path('\''app\/livewire-temp\/'\'' . uniqid() . '\''.tmp'\'');\
        \$tempDir = dirname(\$tempPath);\
        \
        if (!is_dir(\$tempDir)) {\
            mkdir(\$tempDir, 0755, true);\
        }\
        \
        \$stream = \$this->storage->readStream(\$this->path);\
        file_put_contents(\$tempPath, stream_get_contents(\$stream));\
        fclose(\$stream);\
        \
        \$dimensions = @getimagesize(\$tempPath);\
        \
        \/\/ Clean up the temporary file\
        @unlink(\$tempPath);\
        \
        return \$dimensions;/' vendor/livewire/livewire/src/Features/SupportFileUploads/TemporaryUploadedFile.php

echo "✅ Applied second patch"

# Clear all Laravel caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

echo "✅ Cleared all caches"

echo ""
echo "🎉 Patch applied successfully!"
echo "📁 Backup created at: vendor/livewire/livewire/src/Features/SupportFileUploads/TemporaryUploadedFile.php.backup.*"
echo ""
echo "⚠️  IMPORTANT: Please restart your application in the Cloudways dashboard!"
echo "   Go to: Cloudways Dashboard > Your App > Application Settings > Restart Application"
echo ""
echo "🔍 To verify the patch, run:"
echo "   grep -n 'tmpfile()' vendor/livewire/livewire/src/Features/SupportFileUploads/TemporaryUploadedFile.php"
echo "   (Should return no results if patch was successful)" 