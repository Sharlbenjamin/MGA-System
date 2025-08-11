<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DistanceCalculationService
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
     * Calculate distance between File address and Provider Branch Operation Contact address
     *
     * @param \App\Models\File $file
     * @return array|null
     */
    public function calculateFileToBranchDistance($file)
    {
        if (!$file->address) {
            \Log::warning('Distance calculation failed: File address is empty', ['file_id' => $file->id]);
            return null;
        }

        $providerBranch = $file->providerBranch;
        if (!$providerBranch) {
            \Log::warning('Distance calculation failed: No provider branch found', ['file_id' => $file->id]);
            return null;
        }

        $operationContact = $providerBranch->operationContact;
        if (!$operationContact) {
            \Log::warning('Distance calculation failed: No operation contact found', [
                'file_id' => $file->id, 
                'branch_id' => $providerBranch->id
            ]);
            return null;
        }

        if (!$operationContact->address) {
            \Log::warning('Distance calculation failed: Operation contact address is empty', [
                'file_id' => $file->id, 
                'branch_id' => $providerBranch->id,
                'contact_id' => $operationContact->id
            ]);
            return null;
        }

        \Log::info('Distance calculation attempt', [
            'file_id' => $file->id,
            'file_address' => $file->address,
            'branch_id' => $providerBranch->id,
            'contact_address' => $operationContact->address
        ]);

        return $this->calculateDistance($file->address, $operationContact->address);
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

        return $distanceData['duration_minutes'] . ' min';
    }
} 