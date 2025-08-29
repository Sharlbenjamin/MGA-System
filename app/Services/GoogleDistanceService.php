<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleDistanceService
{
    protected $apiKey;
    protected $baseUrl = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key');
    }

    /**
     * Calculate distance and travel time between two addresses
     *
     * @param string $originAddress
     * @param string $destinationAddress
     * @param string $mode 'driving', 'walking', 'bicycling', 'transit'
     * @return array|null
     */
    public function calculateDistance($originAddress, $destinationAddress, $mode = 'driving')
    {
        if (empty($originAddress) || empty($destinationAddress)) {
            return null;
        }

        if (empty($this->apiKey)) {
            Log::warning('Google Maps API key not configured for distance calculation');
            return null;
        }

        try {
            $response = Http::get($this->baseUrl, [
                'origins' => $originAddress,
                'destinations' => $destinationAddress,
                'mode' => $mode,
                'key' => $this->apiKey,
            ]);

            Log::info('Distance Matrix API Response', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'origin' => $originAddress,
                'destination' => $destinationAddress
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'OK' && !empty($data['rows'][0]['elements'][0])) {
                    $element = $data['rows'][0]['elements'][0];

                    if ($element['status'] === 'OK') {
                        return [
                            'distance' => $element['distance']['text'],
                            'distance_meters' => $element['distance']['value'],
                            'duration' => $element['duration']['text'],
                            'duration_seconds' => $element['duration']['value'],
                            'duration_minutes' => round($element['duration']['value'] / 60, 1),
                        ];
                    } else {
                        Log::warning('Distance Matrix element status not OK', [
                            'element_status' => $element['status'],
                            'element' => $element
                        ]);
                    }
                } else {
                    Log::warning('Distance Matrix response not OK', [
                        'response_status' => $data['status'] ?? 'unknown',
                        'data' => $data
                    ]);
                }
            } else {
                Log::error('Distance Matrix API request failed', [
                    'status_code' => $response->status(),
                    'response_body' => $response->body()
                ]);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Distance calculation error', [
                'message' => $e->getMessage(),
                'origin' => $originAddress,
                'destination' => $destinationAddress
            ]);

            return null;
        }
    }

    /**
     * Calculate distance between File address and Provider Branch address
     *
     * @param \App\Models\File $file
     * @param \App\Models\ProviderBranch $branch
     * @return array|null
     */
    public function calculateFileToBranchDistance($file, $branch)
    {
        if (!$file->address) {
            Log::warning('Distance calculation failed: File address is empty', ['file_id' => $file->id]);
            return null;
        }

        // Get branch address - prioritize direct address
        $branchAddress = $branch->address;
        
        // Fallback to operation contact address
        if (!$branchAddress && $branch->operationContact) {
            $branchAddress = $branch->operationContact->address;
        }
        
        // Fallback to GOP contact address
        if (!$branchAddress && $branch->gopContact) {
            $branchAddress = $branch->gopContact->address;
        }
        
        // Fallback to financial contact address
        if (!$branchAddress && $branch->financialContact) {
            $branchAddress = $branch->financialContact->address;
        }

        if (!$branchAddress) {
            Log::warning('Distance calculation failed: No branch address found', [
                'file_id' => $file->id,
                'branch_id' => $branch->id
            ]);
            return null;
        }

        Log::info('Distance calculation using branch address', [
            'file_id' => $file->id,
            'file_address' => $file->address,
            'branch_id' => $branch->id,
            'branch_address' => $branchAddress
        ]);

        return $this->calculateDistance($file->address, $branchAddress);
    }

    /**
     * Get formatted distance string for display
     *
     * @param array|null $distanceData
     * @return string
     */
    public function getFormattedDistance($distanceData)
    {
        if (!$distanceData) {
            return 'N/A';
        }

        return $distanceData['distance'] . ' - ' . $distanceData['duration'];
    }

    /**
     * Get formatted distance with fallback for display
     *
     * @param array|null $distanceData
     * @return string
     */
    public function getFormattedDistanceWithFallback($distanceData)
    {
        if (!$distanceData) {
            return 'N/A';
        }

        $distance = $distanceData['distance'] ?? 'N/A';
        $duration = $distanceData['duration'] ?? 'N/A';
        
        return $distance . ' - ' . $duration;
    }
}
