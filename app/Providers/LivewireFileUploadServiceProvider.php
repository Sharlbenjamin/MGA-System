<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

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
            return new class extends UploadedFile {
                protected $disk;
                protected $storage;
                protected $path;
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
                        } else {
                            file_put_contents($this->tempPath, '');
                        }
                    } else {
                        file_put_contents($this->tempPath, '');
                    }
                    
                    // Call parent constructor with our temporary file
                    parent::__construct($this->tempPath, $this->path);
                }

                public function getPath(): string
                {
                    return $this->storage->path(FileUploadConfiguration::directory());
                }

                public function isValid(): bool
                {
                    return true;
                }

                public function getSize(): int
                {
                    if (app()->runningUnitTests() && str($this->getFilename())->contains('-size=')) {
                        return (int) str($this->getFilename())->between('-size=', '.')->__toString();
                    }

                    return (int) $this->storage->size($this->path);
                }

                public function getMimeType(): string
                {
                    if (app()->runningUnitTests() && str($this->getFilename())->contains('-mimeType=')) {
                        $escapedMimeType = str($this->getFilename())->between('-mimeType=', '-');
                        return (string) $escapedMimeType->replace('_', '/');
                    }

                    $mimeType = $this->storage->mimeType($this->path);

                    if (in_array($mimeType, ['application/octet-stream', 'inode/x-empty', 'application/x-empty'])) {
                        $detector = new \League\MimeTypeDetection\FinfoMimeTypeDetector();
                        $mimeType = $detector->detectMimeTypeFromPath($this->path) ?: 'text/plain';
                    }

                    return $mimeType;
                }

                public function getFilename(): string
                {
                    return $this->getName($this->path);
                }

                public function getRealPath(): string
                {
                    return $this->storage->path($this->path);
                }

                public function getPathname(): string
                {
                    return $this->storage->path($this->path);
                }

                public function getClientOriginalName(): string
                {
                    return $this->extractOriginalNameFromFilePath($this->path);
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

                public function temporaryUrl()
                {
                    if (!$this->isPreviewable()) {
                        throw new \Livewire\Features\SupportFileUploads\FileNotPreviewableException($this);
                    }

                    if ((FileUploadConfiguration::isUsingS3() or FileUploadConfiguration::isUsingGCS()) && ! app()->runningUnitTests()) {
                        return $this->storage->temporaryUrl(
                            $this->path,
                            now()->addDay()->endOfHour(),
                            ['ResponseContentDisposition' => 'attachment; filename="' . urlencode($this->getClientOriginalName()) . '"']
                        );
                    }

                    if (method_exists($this->storage->getAdapter(), 'getTemporaryUrl')) {
                        return $this->storage->temporaryUrl($this->path, now()->addDay());
                    }

                    return \Illuminate\Support\Facades\URL::temporarySignedRoute(
                        'livewire.preview-file', now()->addMinutes(30)->endOfHour(), ['filename' => $this->getFilename()]
                    );
                }

                public function isPreviewable()
                {
                    $supportedPreviewTypes = config('livewire.temporary_file_upload.preview_mimes', [
                        'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
                        'mov', 'avi', 'wmv', 'mp3', 'm4a',
                        'jpg', 'jpeg', 'mpga', 'webp', 'wma',
                    ]);

                    return in_array($this->guessExtension(),  $supportedPreviewTypes);
                }

                public function readStream()
                {
                    return $this->storage->readStream($this->path);
                }

                public function exists()
                {
                    return $this->storage->exists($this->path);
                }

                public function get()
                {
                    return $this->storage->get($this->path);
                }

                public function delete()
                {
                    return $this->storage->delete($this->path);
                }

                public function storeAs($path, $name = null, $options = [])
                {
                    $options = $this->parseOptions($options);
                    $disk = \Illuminate\Support\Arr::pull($options, 'disk') ?: $this->disk;
                    $newPath = trim($path.'/'.$name, '/');

                    Storage::disk($disk)->put(
                        $newPath, $this->storage->readStream($this->path), $options
                    );

                    return $newPath;
                }

                public function hashName($path = null)
                {
                    $hash = str()->random(30);
                    $meta = str('-meta'.base64_encode($this->getClientOriginalName()).'-')->replace('/', '_');
                    $extension = '.'.$this->getClientOriginalExtension();

                    return $hash.$meta.$extension;
                }

                public function extractOriginalNameFromFilePath($path)
                {
                    return TemporaryUploadedFile::extractOriginalNameFromFilePath($path);
                }

                public static function createFromLivewire($filePath)
                {
                    return new static($filePath, FileUploadConfiguration::disk());
                }

                public static function canUnserialize($subject)
                {
                    return TemporaryUploadedFile::canUnserialize($subject);
                }

                public static function unserializeFromLivewireRequest($subject)
                {
                    return TemporaryUploadedFile::unserializeFromLivewireRequest($subject);
                }

                public function serializeForLivewireResponse()
                {
                    return TemporaryUploadedFile::serializeForLivewireResponse($this);
                }

                public static function serializeMultipleForLivewireResponse($files)
                {
                    return TemporaryUploadedFile::serializeMultipleForLivewireResponse($files);
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
