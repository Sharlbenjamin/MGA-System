<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DocumentPathResolver
{
    /**
     * Valid document categories
     */
    public const VALID_CATEGORIES = [
        'gops',
        'medical_reports',
        'prescriptions',
        'bills',
        'invoices',
        'transactions/in',
        'transactions/out',
    ];

    /**
     * Get the directory path for a file and category
     *
     * @param File $file
     * @param string $category
     * @return string
     * @throws InvalidArgumentException
     */
    public function dirFor(File $file, string $category): string
    {
        $this->validateCategory($category);
        $this->validateFile($file);

        return "files/{$file->mga_reference}/{$category}";
    }

    /**
     * Ensure the directory exists on the public disk and return the relative directory path
     *
     * @param File $file
     * @param string $category
     * @return string
     * @throws InvalidArgumentException
     */
    public function ensure(File $file, string $category): string
    {
        $directory = $this->dirFor($file, $category);

        // Create the directory on the public disk if it doesn't exist
        Storage::disk('public')->makeDirectory($directory);

        return $directory;
    }

    /**
     * Get the full path for a document within a category
     *
     * @param File $file
     * @param string $category
     * @param string $filename
     * @return string
     * @throws InvalidArgumentException
     */
    public function pathFor(File $file, string $category, string $filename): string
    {
        $directory = $this->dirFor($file, $category);
        
        return "{$directory}/{$filename}";
    }

    /**
     * Ensure directory exists and return the full path for a document
     *
     * @param File $file
     * @param string $category
     * @param string $filename
     * @return string
     * @throws InvalidArgumentException
     */
    public function ensurePathFor(File $file, string $category, string $filename): string
    {
        $directory = $this->ensure($file, $category);
        
        return "{$directory}/{$filename}";
    }

    /**
     * Get the absolute storage path for a directory
     *
     * @param File $file
     * @param string $category
     * @return string
     * @throws InvalidArgumentException
     */
    public function absolutePathFor(File $file, string $category): string
    {
        $relativePath = $this->dirFor($file, $category);
        
        return Storage::disk('public')->path($relativePath);
    }

    /**
     * Check if a directory exists for a file and category
     *
     * @param File $file
     * @param string $category
     * @return bool
     * @throws InvalidArgumentException
     */
    public function directoryExists(File $file, string $category): bool
    {
        $directory = $this->dirFor($file, $category);
        
        return Storage::disk('public')->exists($directory);
    }

    /**
     * Get all files in a directory for a file and category
     *
     * @param File $file
     * @param string $category
     * @return array
     * @throws InvalidArgumentException
     */
    public function getFilesInDirectory(File $file, string $category): array
    {
        $directory = $this->dirFor($file, $category);
        
        if (!$this->directoryExists($file, $category)) {
            return [];
        }

        return Storage::disk('public')->files($directory);
    }

    /**
     * Validate that the category is valid
     *
     * @param string $category
     * @throws InvalidArgumentException
     */
    private function validateCategory(string $category): void
    {
        if (!in_array($category, self::VALID_CATEGORIES)) {
            throw new InvalidArgumentException(
                "Invalid category '{$category}'. Valid categories are: " . implode(', ', self::VALID_CATEGORIES)
            );
        }
    }

    /**
     * Validate that the file has a mga_reference
     *
     * @param File $file
     * @throws InvalidArgumentException
     */
    private function validateFile(File $file): void
    {
        if (empty($file->mga_reference)) {
            throw new InvalidArgumentException('File must have a mga_reference to create document paths');
        }
    }

    /**
     * Get all valid categories
     *
     * @return array
     */
    public static function getValidCategories(): array
    {
        return self::VALID_CATEGORIES;
    }

    /**
     * Check if a category is valid
     *
     * @param string $category
     * @return bool
     */
    public static function isValidCategory(string $category): bool
    {
        return in_array($category, self::VALID_CATEGORIES);
    }
}
