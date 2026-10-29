#!/bin/bash

# Livewire Patch Script for Cloudways Server
# This script applies the tmpfile() fix to Livewire's TemporaryUploadedFile.php

echo "Applying Livewire tmpfile() patch..."

# Path to the Livewire file on Cloudways server
LIVEWIRE_FILE="/home/1417710.cloudwaysapps.com/fdcpgwbqxd/public_html/vendor/livewire/livewire/src/Features/SupportFileUploads/TemporaryUploadedFile.php"

# Create backup
cp "$LIVEWIRE_FILE" "$LIVEWIRE_FILE.backup.$(date +%Y%m%d_%H%M%S)"

# Create livewire-temp directory
mkdir -p /home/1417710.cloudwaysapps.com/fdcpgwbqxd/public_html/storage/app/livewire-temp
chmod 755 /home/1417710.cloudwaysapps.com/fdcpgwbqxd/public_html/storage/app/livewire-temp

# Apply the patch using sed
sed -i 's/$tempFile = tmpfile();/\
        \/\/ Use a temporary file in the storage directory instead of tmpfile()\
        $tempPath = storage_path('\''app\/livewire-temp\/'\'' . uniqid() . '\''.tmp'\'');\
        $tempDir = dirname($tempPath);\
        \
        if (!is_dir($tempDir)) {\
            mkdir($tempDir, 0755, true);\
        }\
        \
        \/\/ Create a temporary file using file_put_contents instead of tmpfile()\
        file_put_contents($tempPath, '\'''\'');\
        $tempFile = fopen($tempPath, '\''r+'\'');/' "$LIVEWIRE_FILE"

echo "Patch applied successfully!"
echo "Backup created at: $LIVEWIRE_FILE.backup.$(date +%Y%m%d_%H%M%S)"

# Clear Laravel caches
cd /home/1417710.cloudwaysapps.com/fdcpgwbqxd/public_html
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

echo "Laravel caches cleared!"
echo "Please restart your application in the Cloudways dashboard." 