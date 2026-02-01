<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DistanceCalculationService
{
    protected $apiKey;

    protected $distanceMatrixUrl = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    protected $geocodeUrl = 'https://maps.googleapis.com/maps/api/geocode/json';

    protected $lastError = null;

    /** @var string|null Region/country code (e.g. 'ie') to bias geocoding and avoid wrong-country results */
    protected $region;

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key');
        $this->region = config('services.google.maps_region');
    }

    /**
     * Get the last error message
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Geocode an address to coordinates for more accurate distance matrix results.
     * Returns ['lat' => float, 'lng' => float] or null on failure.
     */
    public function geocodeAddress(string $address): ?array
    {
        $address = $this->normalizeAddress($address);
        if ($address === '') {
            return null;
        }

        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $params = [
                'address' => $address,
                'key' => $this->apiKey,
            ];
            if (! empty($this->region)) {
                $params['region'] = $this->region;
            }

            $response = Http::get($this->geocodeUrl, $params);

            if (! $response->successful()) {
                Log::warning('Geocoding request failed', [
                    'status' => $response->status(),
                    'address' => $address,
                ]);
                return null;
            }

            $data = $response->json();
            if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0]['geometry']['location'])) {
                Log::debug('Geocoding returned no result or zero results', [
                    'status' => $data['status'] ?? null,
                    'address' => $address,
                ]);
                return null;
            }

            $loc = $data['results'][0]['geometry']['location'];
            return [
                'lat' => (float) $loc['lat'],
                'lng' => (float) $loc['lng'],
            ];
        } catch (\Exception $e) {
            Log::warning('Geocoding error', ['message' => $e->getMessage(), 'address' => $address]);
            return null;
        }
    }

    /**
     * Normalize address string (trim, collapse whitespace) for consistent API calls.
     */
    protected function normalizeAddress(string $address): string
    {
        $address = trim(preg_replace('/\s+/', ' ', $address));
        return $address;
    }

    /**
     * Calculate distance and travel time between two addresses.
     * Uses geocoding first when possible so the Distance Matrix API gets exact coordinates,
     * which improves accuracy compared to address strings alone.
     *
     * @param string $originAddress
     * @param string $destinationAddress
     * @param string $mode 'driving', 'walking', 'bicycling', 'transit'
     * @return array|null
     */
    public function calculateDistance($originAddress, $destinationAddress, $mode = 'driving')
    {
        $originAddress = $this->normalizeAddress($originAddress ?? '');
        $destinationAddress = $this->normalizeAddress($destinationAddress ?? '');

        if ($originAddress === '' || $destinationAddress === '') {
            return null;
        }

        if (empty($this->apiKey)) {
            $this->lastError = 'API key not configured';
            Log::warning('Google Maps API key not configured for distance calculation');
            return null;
        }

        // Prefer coordinates for accuracy: geocode both addresses, then call Distance Matrix with lat,lng
        $originCoords = $this->geocodeAddress($originAddress);
        $destinationCoords = $this->geocodeAddress($destinationAddress);

        $origins = $originCoords
            ? $originCoords['lat'] . ',' . $originCoords['lng']
            : $originAddress;
        $destinations = $destinationCoords
            ? $destinationCoords['lat'] . ',' . $destinationCoords['lng']
            : $destinationAddress;

        try {
            $response = Http::get($this->distanceMatrixUrl, [
                'origins' => $origins,
                'destinations' => $destinations,
                'mode' => $mode,
                'key' => $this->apiKey,
            ]);

            if (! $response->successful()) {
                $this->lastError = 'HTTP Error: ' . $response->status();
                Log::error('Distance Matrix API request failed', [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'origin' => $originAddress,
                    'destination' => $destinationAddress,
                    'mode' => $mode,
                ]);
                return null;
            }

            $data = $response->json();

            if (isset($data['error_message'])) {
                $this->lastError = $data['error_message'];
                Log::error('Google Maps API Error', [
                    'error_message' => $data['error_message'],
                    'status' => $data['status'] ?? 'unknown',
                    'origin' => $originAddress,
                    'destination' => $destinationAddress,
                ]);
                return null;
            }

            if ($data['status'] !== 'OK' || empty($data['rows'][0]['elements'][0])) {
                $this->lastError = 'API status: ' . ($data['status'] ?? 'UNKNOWN');
                Log::warning('Distance Matrix response not OK', [
                    'response_status' => $data['status'] ?? 'unknown',
                    'data' => $data,
                    'origin' => $originAddress,
                    'destination' => $destinationAddress,
                    'mode' => $mode,
                ]);
                return null;
            }

            $element = $data['rows'][0]['elements'][0];

            if ($element['status'] !== 'OK') {
                $this->lastError = 'Element status: ' . ($element['status'] ?? 'UNKNOWN');
                Log::warning('Distance Matrix element status not OK', [
                    'element_status' => $element['status'],
                    'element' => $element,
                    'origin' => $originAddress,
                    'destination' => $destinationAddress,
                    'mode' => $mode,
                ]);
                return null;
            }

            $this->lastError = null;
            return [
                'distance' => $element['distance']['text'],
                'distance_meters' => $element['distance']['value'],
                'duration' => $element['duration']['text'],
                'duration_seconds' => $element['duration']['value'],
                'duration_minutes' => round($element['duration']['value'] / 60, 1),
            ];
        } catch (\Exception $e) {
            Log::error('Distance calculation error', [
                'message' => $e->getMessage(),
                'origin' => $originAddress,
                'destination' => $destinationAddress,
            ]);
            return null;
        }
    }

    /**
     * Calculate distance between File address and Provider Branch address
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

        // First try to use the direct address field on the branch
        if ($providerBranch->address) {
            \Log::info('Distance calculation using direct branch address', [
                'file_id' => $file->id,
                'file_address' => $file->address,
                'branch_id' => $providerBranch->id,
                'branch_address' => $providerBranch->address
            ]);

            return $this->calculateDistance($file->address, $providerBranch->address);
        }

        // Fallback to operation contact address
        $operationContact = $providerBranch->operationContact;
        if (!$operationContact) {
            \Log::warning('Distance calculation failed: No branch address or operation contact found', [
                'file_id' => $file->id, 
                'branch_id' => $providerBranch->id
            ]);
            return null;
        }

        if (!$operationContact->address) {
            \Log::warning('Distance calculation failed: No branch address or operation contact address', [
                'file_id' => $file->id, 
                'branch_id' => $providerBranch->id,
                'contact_id' => $operationContact->id
            ]);
            return null;
        }

        \Log::info('Distance calculation using operation contact address', [
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