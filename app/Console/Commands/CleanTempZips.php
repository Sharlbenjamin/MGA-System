<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CleanTempZips extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'clean:temp-zips 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--older-than=24 : Delete files older than specified hours (default: 24)}
                            {--force : Delete all files regardless of age}';

    /**
     * The console command description.
     */
    protected $description = 'Clean temporary ZIP files and Livewire temp files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $olderThanHours = (int) $this->option('older-than');
        $force = $this->option('force');

        $this->info('Starting temp file cleanup...');
        $this->info('Dry run: ' . ($dryRun ? 'Yes' : 'No'));
        $this->info('Older than: ' . $olderThanHours . ' hours');
        $this->info('Force delete all: ' . ($force ? 'Yes' : 'No'));

        $totalDeleted = 0;
        $totalSize = 0;

        // Clean storage/app/temp/*.zip files
        $tempZipResult = $this->cleanTempZipFiles($dryRun, $olderThanHours, $force);
        $totalDeleted += $tempZipResult['deleted'];
        $totalSize += $tempZipResult['size'];

        // Clean Livewire temp files
        $livewireResult = $this->cleanLivewireTempFiles($dryRun, $olderThanHours, $force);
        $totalDeleted += $livewireResult['deleted'];
        $totalSize += $livewireResult['size'];

        // Clean other temp directories
        $otherResult = $this->cleanOtherTempFiles($dryRun, $olderThanHours, $force);
        $totalDeleted += $otherResult['deleted'];
        $totalSize += $otherResult['size'];

        $this->info("\n" . str_repeat('=', 50));
        $this->info("Cleanup Summary:");
        $this->info("Files deleted: {$totalDeleted}");
        $this->info("Space freed: " . $this->formatBytes($totalSize));

        if ($dryRun) {
            $this->info("\nThis was a dry run. No files were actually deleted.");
        } else {
            $this->info("\nCleanup completed successfully.");
        }

        return 0;
    }

    /**
     * Clean temporary ZIP files from storage/app/temp/
     */
    protected function cleanTempZipFiles(bool $dryRun, int $olderThanHours, bool $force): array
    {
        $this->info("\nCleaning storage/app/temp/*.zip files...");
        
        $tempDir = storage_path('app/temp');
        $deleted = 0;
        $size = 0;

        if (!is_dir($tempDir)) {
            $this->warn("Temp directory does not exist: {$tempDir}");
            return ['deleted' => 0, 'size' => 0];
        }

        $files = glob($tempDir . '/*.zip');
        
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $fileAge = $this->getFileAge($file);
            $fileSize = filesize($file);
            $fileName = basename($file);

            // Check if file should be deleted
            if ($force || $fileAge > $olderThanHours) {
                if ($dryRun) {
                    $this->line("  Would delete: {$fileName} (age: {$fileAge}h, size: " . $this->formatBytes($fileSize) . ")");
                } else {
                    if (unlink($file)) {
                        $this->line("  Deleted: {$fileName} (age: {$fileAge}h, size: " . $this->formatBytes($fileSize) . ")");
                        $deleted++;
                        $size += $fileSize;
                    } else {
                        $this->error("  Failed to delete: {$fileName}");
                    }
                }
            } else {
                $this->line("  Skipped: {$fileName} (age: {$fileAge}h - too recent)");
            }
        }

        if (empty($files)) {
            $this->info("  No ZIP files found in temp directory.");
        }

        return ['deleted' => $deleted, 'size' => $size];
    }

    /**
     * Clean Livewire temporary files
     */
    protected function cleanLivewireTempFiles(bool $dryRun, int $olderThanHours, bool $force): array
    {
        $this->info("\nCleaning Livewire temp files...");
        
        $livewireTempDir = storage_path('app/livewire-tmp');
        $deleted = 0;
        $size = 0;

        if (!is_dir($livewireTempDir)) {
            $this->warn("Livewire temp directory does not exist: {$livewireTempDir}");
            return ['deleted' => 0, 'size' => 0];
        }

        $files = $this->getFilesRecursively($livewireTempDir);
        
        foreach ($files as $file) {
            $fileAge = $this->getFileAge($file);
            $fileSize = filesize($file);
            $relativePath = str_replace($livewireTempDir . '/', '', $file);

            // Check if file should be deleted
            if ($force || $fileAge > $olderThanHours) {
                if ($dryRun) {
                    $this->line("  Would delete: {$relativePath} (age: {$fileAge}h, size: " . $this->formatBytes($fileSize) . ")");
                } else {
                    if (unlink($file)) {
                        $this->line("  Deleted: {$relativePath} (age: {$fileAge}h, size: " . $this->formatBytes($fileSize) . ")");
                        $deleted++;
                        $size += $fileSize;
                    } else {
                        $this->error("  Failed to delete: {$relativePath}");
                    }
                }
            } else {
                $this->line("  Skipped: {$relativePath} (age: {$fileAge}h - too recent)");
            }
        }

        // Clean empty directories
        $this->cleanEmptyDirectories($livewireTempDir, $dryRun);

        if (empty($files)) {
            $this->info("  No Livewire temp files found.");
        }

        return ['deleted' => $deleted, 'size' => $size];
    }

    /**
     * Clean other temporary files
     */
    protected function cleanOtherTempFiles(bool $dryRun, int $olderThanHours, bool $force): array
    {
        $this->info("\nCleaning other temp files...");
        
        $tempDirs = [
            storage_path('app/temp'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        $deleted = 0;
        $size = 0;

        foreach ($tempDirs as $tempDir) {
            if (!is_dir($tempDir)) {
                continue;
            }

            $this->info("  Checking: " . basename($tempDir));
            
            // Clean temporary files (not ZIP files, already handled)
            $patterns = ['*.tmp', '*.temp', '*.lock', '*.pid'];
            
            foreach ($patterns as $pattern) {
                $files = glob($tempDir . '/' . $pattern);
                
                foreach ($files as $file) {
                    if (!is_file($file)) {
                        continue;
                    }

                    $fileAge = $this->getFileAge($file);
                    $fileSize = filesize($file);
                    $fileName = basename($file);

                    // Check if file should be deleted
                    if ($force || $fileAge > $olderThanHours) {
                        if ($dryRun) {
                            $this->line("    Would delete: {$fileName} (age: {$fileAge}h, size: " . $this->formatBytes($fileSize) . ")");
                        } else {
                            if (unlink($file)) {
                                $this->line("    Deleted: {$fileName} (age: {$fileAge}h, size: " . $this->formatBytes($fileSize) . ")");
                                $deleted++;
                                $size += $fileSize;
                            } else {
                                $this->error("    Failed to delete: {$fileName}");
                            }
                        }
                    }
                }
            }
        }

        return ['deleted' => $deleted, 'size' => $size];
    }

    /**
     * Get files recursively from directory
     */
    protected function getFilesRecursively(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Clean empty directories
     */
    protected function cleanEmptyDirectories(string $directory, bool $dryRun): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $dirPath = $file->getPathname();
                if (count(scandir($dirPath)) === 2) { // Only . and .. entries
                    if ($dryRun) {
                        $this->line("  Would remove empty directory: " . str_replace($directory . '/', '', $dirPath));
                    } else {
                        if (rmdir($dirPath)) {
                            $this->line("  Removed empty directory: " . str_replace($directory . '/', '', $dirPath));
                        }
                    }
                }
            }
        }
    }

    /**
     * Get file age in hours
     */
    protected function getFileAge(string $filePath): float
    {
        $fileTime = filemtime($filePath);
        $now = time();
        return ($now - $fileTime) / 3600; // Convert seconds to hours
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
