<?php

namespace App\Filament\Resources\FileResource\Pages\Components;

use Filament\Forms\Components\Component;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SimpleFileUpload extends Component
{
    protected string $view = 'filament.forms.components.simple-file-upload';
    
    protected string $directory;
    protected string $disk = 'public';
    protected array $acceptedFileTypes = ['application/pdf', 'image/*'];
    protected int $maxFileSize = 10240; // 10MB
    protected string $documentType;
    protected $record;
    
    public static function make(string $name): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();
        
        return $static;
    }
    
    public function directory(string $directory): static
    {
        $this->directory = $directory;
        return $this;
    }
    
    public function disk(string $disk): static
    {
        $this->disk = $disk;
        return $this;
    }
    
    public function acceptedFileTypes(array $types): static
    {
        $this->acceptedFileTypes = $types;
        return $this;
    }
    
    public function maxFileSize(int $size): static
    {
        $this->maxFileSize = $size;
        return $this;
    }
    
    public function documentType(string $type): static
    {
        $this->documentType = $type;
        return $this;
    }
    
    public function record($record): static
    {
        $this->record = $record;
        return $this;
    }
    
    public function getDirectory(): string
    {
        return $this->directory;
    }
    
    public function getDisk(): string
    {
        return $this->disk;
    }
    
    public function getAcceptedFileTypes(): array
    {
        return $this->acceptedFileTypes;
    }
    
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }
    
    public function getDocumentType(): string
    {
        return $this->documentType;
    }
    
    public function getRecord(): mixed
    {
        return $this->record;
    }
    
    public function handleFileUpload($file): array
    {
        try {
            // Validate file
            if (!$file) {
                throw new \Exception('No file provided');
            }
            
            // Validate file size
            if ($file->getSize() > $this->maxFileSize * 1024) {
                throw new \Exception('File size exceeds maximum allowed size of ' . $this->maxFileSize . 'KB');
            }
            
            // Validate file type
            $mimeType = $file->getMimeType();
            $isValidType = false;
            foreach ($this->acceptedFileTypes as $acceptedType) {
                if ($acceptedType === 'image/*' && str_starts_with($mimeType, 'image/')) {
                    $isValidType = true;
                    break;
                } elseif ($acceptedType === $mimeType) {
                    $isValidType = true;
                    break;
                }
            }
            
            if (!$isValidType) {
                throw new \Exception('Invalid file type. Accepted types: ' . implode(', ', $this->acceptedFileTypes));
            }
            
            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid() . '_' . time() . '.' . $extension;
            
            // Store file
            $filePath = $this->directory . '/' . $filename;
            Storage::disk($this->disk)->put($filePath, file_get_contents($file->getRealPath()));
            
            return [
                'success' => true,
                'file_path' => $filePath,
                'filename' => $filename,
                'size' => $file->getSize(),
                'mime_type' => $mimeType
            ];
            
        } catch (\Exception $e) {
            Log::error('Simple file upload failed', [
                'error' => $e->getMessage(),
                'document_type' => $this->documentType,
                'directory' => $this->directory
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
