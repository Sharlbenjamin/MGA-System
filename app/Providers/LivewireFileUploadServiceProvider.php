<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Illuminate\Support\Facades\Storage;

class LivewireFileUploadServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Override the TemporaryUploadedFile class to avoid tmpfile() issues
        $this->app->singleton(TemporaryUploadedFile::class, function ($app) {
            return new class extends TemporaryUploadedFile {
                public function __construct($path, $disk)
                {
                    $this->disk = $disk;
                    $this->storage = Storage::disk($this->disk);
                    $this->path = FileUploadConfiguration::path($path, false);

                    // Use a temporary file in the storage directory instead of tmpfile()
                    $tempPath = storage_path('app/livewire-temp/' . uniqid() . '.tmp');
                    $tempDir = dirname($tempPath);
                    
                    if (!is_dir($tempDir)) {
                        mkdir($tempDir, 0755, true);
                    }

                    // Copy the uploaded file to our temporary location
                    if ($this->storage->exists($this->path)) {
                        $stream = $this->storage->readStream($this->path);
                        file_put_contents($tempPath, stream_get_contents($stream));
                        fclose($stream);
                    }
                    
                    parent::__construct($tempPath, $this->path);
                }

                public function dimensions()
                {
                    // Use a temporary file in the storage directory instead of tmpfile()
                    $tempPath = storage_path('app/livewire-temp/' . uniqid() . '.tmp');
                    $tempDir = dirname($tempPath);
                    
                    if (!is_dir($tempDir)) {
                        mkdir($tempDir, 0755, true);
                    }

                    if ($this->storage->exists($this->path)) {
                        $stream = $this->storage->readStream($this->path);
                        file_put_contents($tempPath, stream_get_contents($stream));
                        fclose($stream);

                        $dimensions = @getimagesize($tempPath);
                        
                        // Clean up the temporary file
                        @unlink($tempPath);
                        
                        return $dimensions;
                    }
                    
                    return false;
                }

                public function getRealPath()
                {
                    // Return the actual file path for the uploaded file
                    return $this->storage->path($this->path);
                }

                public function getPathname()
                {
                    // Return the actual file path for the uploaded file
                    return $this->storage->path($this->path);
                }
            };
        });
    }
}
