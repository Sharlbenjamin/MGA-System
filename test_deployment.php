<?php

/**
 * Deployment Test Script
 * 
 * Run this script after deployment to verify all components are working correctly.
 * Usage: php test_deployment.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== MGA System Deployment Test ===\n\n";

$tests = [];
$passed = 0;
$failed = 0;

// Test 1: Database Connection
echo "1. Testing database connection...\n";
try {
    DB::connection()->getPdo();
    $tests[] = "✓ Database connection successful";
    $passed++;
} catch (Exception $e) {
    $tests[] = "✗ Database connection failed: " . $e->getMessage();
    $failed++;
}

// Test 2: Storage Link
echo "2. Testing storage link...\n";
try {
    $linkPath = public_path('storage');
    if (is_link($linkPath) && readlink($linkPath) === storage_path('app/public')) {
        $tests[] = "✓ Storage link is correct";
        $passed++;
    } else {
        $tests[] = "✗ Storage link is missing or incorrect";
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = "✗ Storage link test failed: " . $e->getMessage();
    $failed++;
}

// Test 3: Public Disk Access
echo "3. Testing public disk access...\n";
try {
    $testFile = 'test_deployment_' . time() . '.txt';
    $testContent = 'Deployment test file';
    
    Storage::disk('public')->put($testFile, $testContent);
    $retrieved = Storage::disk('public')->get($testFile);
    
    if ($retrieved === $testContent) {
        Storage::disk('public')->delete($testFile);
        $tests[] = "✓ Public disk read/write successful";
        $passed++;
    } else {
        $tests[] = "✗ Public disk read/write failed";
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = "✗ Public disk test failed: " . $e->getMessage();
    $failed++;
}

// Test 4: Document Path Columns
echo "4. Testing document path columns...\n";
try {
    $columns = [
        'invoices' => 'invoice_document_path',
        'bills' => 'bill_document_path',
        'medical_reports' => 'document_path',
        'prescriptions' => 'document_path',
        'gops' => 'document_path',
    ];
    
    $allColumnsExist = true;
    foreach ($columns as $table => $column) {
        if (!DB::getSchemaBuilder()->hasColumn($table, $column)) {
            $allColumnsExist = false;
            break;
        }
    }
    
    if ($allColumnsExist) {
        $tests[] = "✓ All document path columns exist";
        $passed++;
    } else {
        $tests[] = "✗ Some document path columns are missing";
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = "✗ Document path columns test failed: " . $e->getMessage();
    $failed++;
}

// Test 5: Backfill Logs Table
echo "5. Testing backfill logs table...\n";
try {
    if (DB::getSchemaBuilder()->hasTable('backfill_logs')) {
        $tests[] = "✓ Backfill logs table exists";
        $passed++;
    } else {
        $tests[] = "✗ Backfill logs table is missing";
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = "✗ Backfill logs table test failed: " . $e->getMessage();
    $failed++;
}

// Test 6: Google Drive Service
echo "6. Testing Google Drive service...\n";
try {
    $downloader = app(\App\Services\GoogleDriveFileDownloader::class);
    if ($downloader) {
        $tests[] = "✓ Google Drive service is available";
        $passed++;
    } else {
        $tests[] = "✗ Google Drive service is not available";
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = "✗ Google Drive service test failed: " . $e->getMessage();
    $failed++;
}

// Test 7: Document Path Resolver
echo "7. Testing Document Path Resolver...\n";
try {
    $resolver = app(\App\Services\DocumentPathResolver::class);
    if ($resolver) {
        $tests[] = "✓ Document Path Resolver is available";
        $passed++;
    } else {
        $tests[] = "✗ Document Path Resolver is not available";
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = "✗ Document Path Resolver test failed: " . $e->getMessage();
    $failed++;
}

// Test 8: Commands Availability
echo "8. Testing new commands...\n";
try {
    $commands = [
        'backfill:drive-documents',
        'clean:temp-zips',
    ];
    
    $allCommandsExist = true;
    foreach ($commands as $command) {
        $exitCode = 0;
        $output = [];
        exec("php artisan list | grep '{$command}'", $output, $exitCode);
        if (empty($output)) {
            $allCommandsExist = false;
            break;
        }
    }
    
    if ($allCommandsExist) {
        $tests[] = "✓ All new commands are available";
        $passed++;
    } else {
        $tests[] = "✗ Some commands are missing";
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = "✗ Commands test failed: " . $e->getMessage();
    $failed++;
}

// Test 9: Routes
echo "9. Testing new routes...\n";
try {
    $routes = [
        'docs.serve',
        'docs.metadata',
        'files.export.zip',
    ];
    
    $allRoutesExist = true;
    foreach ($routes as $route) {
        try {
            $url = route($route, ['type' => 'test', 'id' => 1], false);
            if (!$url) {
                $allRoutesExist = false;
                break;
            }
        } catch (Exception $e) {
            $allRoutesExist = false;
            break;
        }
    }
    
    if ($allRoutesExist) {
        $tests[] = "✓ All new routes are registered";
        $passed++;
    } else {
        $tests[] = "✗ Some routes are missing";
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = "✗ Routes test failed: " . $e->getMessage();
    $failed++;
}

// Test 10: Model Methods
echo "10. Testing model methods...\n";
try {
    $models = [
        \App\Models\Invoice::class,
        \App\Models\Bill::class,
        \App\Models\Gop::class,
        \App\Models\MedicalReport::class,
        \App\Models\Prescription::class,
        \App\Models\Transaction::class,
    ];
    
    $allMethodsExist = true;
    foreach ($models as $model) {
        if (!method_exists($model, 'getDocumentSignedUrl') || 
            !method_exists($model, 'hasLocalDocument')) {
            $allMethodsExist = false;
            break;
        }
    }
    
    if ($allMethodsExist) {
        $tests[] = "✓ All model methods are available";
        $passed++;
    } else {
        $tests[] = "✗ Some model methods are missing";
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = "✗ Model methods test failed: " . $e->getMessage();
    $failed++;
}

// Print Results
echo "\n=== Test Results ===\n";
foreach ($tests as $test) {
    echo $test . "\n";
}

echo "\n=== Summary ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo "Total: " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n🎉 All tests passed! Deployment is successful.\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed. Please check the issues above.\n";
    exit(1);
}
