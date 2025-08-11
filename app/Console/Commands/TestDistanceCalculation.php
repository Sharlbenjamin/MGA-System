<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DistanceCalculationService;
use App\Models\File;

class TestDistanceCalculation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:distance {--file-id= : Test with specific file ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test distance calculation between File address and Provider Branch Operation Contact address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Distance Calculation Service...');
        
        $service = new DistanceCalculationService();
        
        // Test basic functionality
        $this->info('1. Testing basic functionality...');
        $result = $service->calculateDistance('', '');
        $this->line('Empty addresses test: ' . ($result === null ? 'PASS' : 'FAIL'));
        
        $formatted = $service->getFormattedDistance(null);
        $this->line('Formatted distance test: ' . ($formatted === 'N/A' ? 'PASS' : 'FAIL'));
        
        // Test with real data if file ID provided
        if ($fileId = $this->option('file-id')) {
            $this->info("2. Testing with File ID: {$fileId}");
            
            $file = File::with(['providerBranch.operationContact'])->find($fileId);
            
            if (!$file) {
                $this->error("File with ID {$fileId} not found!");
                return 1;
            }
            
            $this->line("File Address: " . ($file->address ?: 'Not set'));
            
            if ($file->providerBranch) {
                $this->line("Provider Branch: " . $file->providerBranch->branch_name);
                
                if ($file->providerBranch->operationContact) {
                    $this->line("Operation Contact Address: " . ($file->providerBranch->operationContact->address ?: 'Not set'));
                    
                    $distanceData = $service->calculateFileToBranchDistance($file);
                    
                    if ($distanceData) {
                        $this->info("Distance calculation successful!");
                        $this->line("Distance: " . $distanceData['distance']);
                        $this->line("Duration: " . $distanceData['duration']);
                        $this->line("Duration (minutes): " . $distanceData['duration_minutes']);
                        $this->line("Formatted: " . $service->getFormattedDistance($distanceData));
                    } else {
                        $this->warn("Distance calculation failed or returned null");
                    }
                } else {
                    $this->warn("No operation contact found for this provider branch");
                }
            } else {
                $this->warn("No provider branch found for this file");
            }
        } else {
            $this->info('2. No file ID provided. Use --file-id=123 to test with real data.');
        }
        
        // Test API key configuration
        $this->info('3. Checking API key configuration...');
        $apiKey = config('services.google.maps_api_key');
        if ($apiKey) {
            $this->line('API key is configured');
        } else {
            $this->warn('API key is not configured. Add GOOGLE_MAPS_API_KEY to your .env file');
        }
        
        $this->info('Distance calculation test completed!');
        
        return 0;
    }
} 