<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PdfDecompressor\Normalizer;
use setasign\Fpdi\Fpdi;

/**
 * Rewrites modern PDFs into a classic structure the free FPDI parser can read.
 */
class PdfFpdiCompatibilityService
{
    private static bool $autoloaderRegistered = false;

    public function ensureNormalizerAvailable(): bool
    {
        if (class_exists(Normalizer::class, true)) {
            return true;
        }

        $this->registerAutoload();

        return class_exists(Normalizer::class, true);
    }

    /**
     * @return array{path: ?string, error: ?string}
     */
    public function normalizeForFpdi(string $sourcePath): array
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            return ['path' => null, 'error' => 'Bill PDF file is missing or unreadable.'];
        }

        if (! $this->ensureNormalizerAvailable()) {
            return [
                'path' => null,
                'error' => 'PDF compatibility library source is missing (expected third-party/php-pdf-decompressor).',
            ];
        }

        $outputPath = $this->makeTempPdfPath();

        try {
            (new Normalizer)->normalizeFile($sourcePath, $outputPath);
        } catch (\Throwable $e) {
            @unlink($outputPath);

            Log::warning('PDF FPDI normalize failed', [
                'path' => $sourcePath,
                'error' => $e->getMessage(),
            ]);

            return ['path' => null, 'error' => 'Normalize failed: '.$e->getMessage()];
        }

        if (! is_file($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);

            return ['path' => null, 'error' => 'Normalize produced an empty file.'];
        }

        try {
            $probe = new Fpdi;
            $pages = $probe->setSourceFile($outputPath);

            if ($pages < 1) {
                @unlink($outputPath);

                return ['path' => null, 'error' => 'Normalized PDF has no pages.'];
            }
        } catch (\Throwable $e) {
            @unlink($outputPath);

            Log::warning('PDF FPDI normalize output still unreadable', [
                'path' => $sourcePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'path' => null,
                'error' => 'Normalized PDF still unreadable by FPDI: '.$e->getMessage(),
            ];
        }

        return ['path' => $outputPath, 'error' => null];
    }

    public function makeTempPdfPath(): string
    {
        // Stay inside the app tree so Cloudways open_basedir cannot block writes
        // (sys_get_temp_dir() often points outside the allowed paths).
        $directory = dirname(__DIR__, 2).'/storage/app/tmp/pdf-fpdi';

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.'/norm_'.uniqid('', true).'.pdf';
    }

    /**
     * @return list<string>
     */
    public function sourceDirectories(): array
    {
        $root = dirname(__DIR__, 2);

        return array_values(array_filter([
            // Prefer the vendored copy shipped with the app (no Composer install needed).
            $root.'/third-party/php-pdf-decompressor/src',
            // Fall back to the Composer package if present.
            $root.'/vendor/drainerlight/php-pdf-decompressor/src',
        ], 'is_dir'));
    }

    protected function registerAutoload(): void
    {
        if (self::$autoloaderRegistered) {
            return;
        }

        self::$autoloaderRegistered = true;

        $bases = $this->sourceDirectories();

        if ($bases === []) {
            return;
        }

        spl_autoload_register(static function (string $class) use ($bases): void {
            $prefix = 'PdfDecompressor\\';

            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

            foreach ($bases as $base) {
                $file = $base.'/'.$relative;

                if (is_file($file)) {
                    require_once $file;

                    return;
                }
            }
        });
    }
}
