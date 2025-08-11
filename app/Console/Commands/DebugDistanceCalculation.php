<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DistanceCalculationService;
use App\Models\File;
use App\Models\ProviderBranch;
use App\Models\Contact;

class DebugDistanceCalculation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:distance {file-id} {--branch-id= : Provider Branch ID to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug distance calculation for a specific file and provider branch';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileId = $this->argument('file-id');
        $branchId = $this->option('branch-id');
        
        $this->info("=== Distance Calculation Debug for File ID: {$fileId} ===");
        
        // 1. Check if file exists
        $file = File::with(['providerBranch.operationContact'])->find($fileId);
        if (!$file) {
            $this->error("❌ File with ID {$fileId} not found!");
            return 1;
        }
        
        $this->info("✅ File found: {$file->mga_reference}");
        
        // 2. Check file address
        $this->info("\n--- File Address Check ---");
        if ($file->address) {
            $this->info("✅ File address: {$file->address}");
        } else {
            $this->error("❌ File address is empty or null");
        }
        
        // 3. Check provider branch
        $this->info("\n--- Provider Branch Check ---");
        if ($file->providerBranch) {
            $this->info("✅ Provider Branch: {$file->providerBranch->branch_name} (ID: {$file->providerBranch->id})");
        } else {
            $this->error("❌ No provider branch assigned to this file");
        }
        
        // 4. Check operation contact
        $this->info("\n--- Operation Contact Check ---");
        if ($file->providerBranch && $file->providerBranch->operationContact) {
            $operationContact = $file->providerBranch->operationContact;
            $this->info("✅ Operation Contact: {$operationContact->name} (ID: {$operationContact->id})");
            
            if ($operationContact->address) {
                $this->info("✅ Operation Contact address: {$operationContact->address}");
            } else {
                $this->error("❌ Operation Contact address is empty or null");
            }
        } else {
            $this->error("❌ No operation contact found for provider branch");
        }
        
        // 5. Check API key
        $this->info("\n--- API Key Check ---");
        $apiKey = config('services.google.maps_api_key');
        if ($apiKey) {
            $this->info("✅ Google Maps API key is configured");
            $this->line("   Key preview: " . substr($apiKey, 0, 10) . "...");
        } else {
            $this->error("❌ Google Maps API key is not configured");
            $this->line("   Add GOOGLE_MAPS_API_KEY to your .env file");
        }
        
        // 6. Test distance calculation
        $this->info("\n--- Distance Calculation Test ---");
        $service = new DistanceCalculationService();
        
        if ($file->address && $file->providerBranch && $file->providerBranch->operationContact && $file->providerBranch->operationContact->address) {
            $this->info("Attempting distance calculation...");
            
            try {
                $distanceData = $service->calculateFileToBranchDistance($file);
                
                if ($distanceData) {
                    $this->info("✅ Distance calculation successful!");
                    $this->line("   Distance: {$distanceData['distance']}");
                    $this->line("   Duration: {$distanceData['duration']}");
                    $this->line("   Duration (minutes): {$distanceData['duration_minutes']}");
                    $this->line("   Formatted: " . $service->getFormattedDistance($distanceData));
                } else {
                    $this->error("❌ Distance calculation returned null");
                    $this->line("   This could be due to:");
                    $this->line("   - API key issues");
                    $this->line("   - Network problems");
                    $this->line("   - Invalid addresses");
                    $this->line("   - API quota exceeded");
                }
            } catch (\Exception $e) {
                $this->error("❌ Exception during distance calculation: " . $e->getMessage());
            }
        } else {
            $this->error("❌ Cannot calculate distance - missing required data");
        }
        
        // 7. Test with specific branch if provided
        if ($branchId) {
            $this->info("\n--- Testing with Specific Branch ID: {$branchId} ---");
            $branch = ProviderBranch::with('operationContact')->find($branchId);
            
            if ($branch) {
                $this->info("✅ Branch found: {$branch->branch_name}");
                
                if ($branch->operationContact) {
                    $this->info("✅ Branch operation contact: {$branch->operationContact->name}");
                    
                    if ($branch->operationContact->address) {
                        $this->info("✅ Branch operation contact address: {$branch->operationContact->address}");
                        
                        // Test direct distance calculation
                        $this->info("Testing direct distance calculation...");
                        $directResult = $service->calculateDistance($file->address, $branch->operationContact->address);
                        
                        if ($directResult) {
                            $this->info("✅ Direct calculation successful!");
                            $this->line("   Distance: {$directResult['distance']}");
                            $this->line("   Duration: {$directResult['duration']}");
                            $this->line("   Duration (minutes): {$directResult['duration_minutes']}");
                        } else {
                            $this->error("❌ Direct calculation failed");
                        }
                    } else {
                        $this->error("❌ Branch operation contact address is empty");
                    }
                } else {
                    $this->error("❌ Branch has no operation contact");
                }
            } else {
                $this->error("❌ Branch with ID {$branchId} not found");
            }
        }
        
        $this->info("\n=== Debug Complete ===");
        
        return 0;
    }
} 