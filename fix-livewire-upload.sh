#!/bin/bash

# Livewire Upload Fix Script for Production
# This script fixes the tmpfile() issue in Livewire file uploads

echo "Applying Livewire tmpfile() fix..."

# Path to the Livewire file on production server
LIVEWIRE_FILE="vendor/livewire/livewire/src/Features/SupportFileUploads/TemporaryUploadedFile.php"

# Create backup
cp "$LIVEWIRE_FILE" "$LIVEWIRE_FILE.backup.$(date +%Y%m%d_%H%M%S)"

# Create livewire-temp directory
mkdir -p storage/app/livewire-temp
chmod 755 storage/app/livewire-temp

# Apply the fix to the constructor
sed -i 's/file_put_contents($tempPath, '\''\'');/if ($this->storage->exists($this->path)) {\n            $stream = $this->storage->readStream($this->path);\n            if ($stream) {\n                file_put_contents($tempPath, stream_get_contents($stream));\n                fclose($stream);\n            } else {\n                file_put_contents($tempPath, '\''\'');\n            }\n        } else {\n            file_put_contents($tempPath, '\''\'');\n        }/' "$LIVEWIRE_FILE"

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

echo "Backup created at: $LIVEWIRE_FILE.backup.$(date +%Y%m%d_%H%M%S)"
echo "Livewire tmpfile() fix applied successfully!" 