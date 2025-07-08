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
                protected $tempPath;
                
                public function __construct($path, $disk)
                {
                    $this->disk = $disk;
                    $this->storage = Storage::disk($this->disk);
                    $this->path = FileUploadConfiguration::path($path, false);

                    // Create a temporary file in the storage directory instead of using tmpfile()
                    $this->tempPath = storage_path('app/livewire-temp/' . uniqid() . '.tmp');
                    $tempDir = dirname($this->tempPath);
                    
                    if (!is_dir($tempDir)) {
                        mkdir($tempDir, 0755, true);
                    }

                    // Copy the uploaded file to our temporary location
                    if ($this->storage->exists($this->path)) {
                        $stream = $this->storage->readStream($this->path);
                        if ($stream) {
                            file_put_contents($this->tempPath, stream_get_contents($stream));
                            fclose($stream);
                        }
                    }
                    
                    // Call parent constructor with our temporary file
                    parent::__construct($this->tempPath, $this->path);
                }

                public function dimensions()
                {
                    if (!$this->storage->exists($this->path)) {
                        return false;
                    }

                    // Create a temporary file for image processing
                    $tempPath = storage_path('app/livewire-temp/' . uniqid() . '.tmp');
                    $tempDir = dirname($tempPath);
                    
                    if (!is_dir($tempDir)) {
                        mkdir($tempDir, 0755, true);
                    }

                    try {
                        $stream = $this->storage->readStream($this->path);
                        if ($stream) {
                            file_put_contents($tempPath, stream_get_contents($stream));
                            fclose($stream);

                            $dimensions = @getimagesize($tempPath);
                            
                            // Clean up the temporary file
                            @unlink($tempPath);
                            
                            return $dimensions;
                        }
                    } catch (\Exception $e) {
                        // Clean up on error
                        @unlink($tempPath);
                    }
                    
                    return false;
                }

                public function __destruct()
                {
                    // Clean up temporary file
                    if (file_exists($this->tempPath)) {
                        @unlink($this->tempPath);
                    }
                }
            };
        });
    }
}
