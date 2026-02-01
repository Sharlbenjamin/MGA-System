# Distance Calculation Feature

## Overview

This feature calculates the travel time by car between:
- **X**: The address in the File model
- **Y**: The address of the Operation Contact of the Provider Branch of the File

The distance is calculated on-the-fly using **Google Geocoding API** (to resolve addresses to coordinates) and **Google Distance Matrix API** (to get driving distance/duration). Using coordinates instead of raw address strings improves accuracy by avoiding ambiguous address resolution.

## Configuration

### 1. Google Maps API Key

Add your Google Maps API key to your `.env` file:

```env
GOOGLE_MAPS_API_KEY=your_google_maps_api_key_here
```

### 2. Optional: Region for accurate geocoding

To avoid addresses being resolved in the wrong country, set a region (e.g. Ireland):

```env
GOOGLE_MAPS_REGION=ie
```

If unset, the default is `ie`. Use [ISO 3166-1](https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2) two-letter country/region codes.

### 3. Enable Google APIs

In Google Cloud Console, enable for your API key:
- **Geocoding API** (used to convert addresses to coordinates)
- **Distance Matrix API**

## Implementation Details

### Files Modified/Created

1. **`app/Services/DistanceCalculationService.php`** - New service for distance calculations
2. **`app/Models/File.php`** - Added distance calculation methods
3. **`app/Filament/Resources/AppointmentResource.php`** - Added distance column
4. **`app/Filament/Resources/FileResource/RelationManagers/AppointmentsRelationManager.php`** - Added distance column
5. **`config/services.php`** - Added Google Maps API key configuration

### Service Methods

#### `DistanceCalculationService`

- `geocodeAddress($address)` - Resolve an address to lat/lng (used internally for accuracy)
- `calculateDistance($originAddress, $destinationAddress, $mode = 'driving')` - Calculate distance between two addresses (geocodes first when possible for better accuracy)
- `calculateFileToBranchDistance($file)` - Calculate distance from File to Provider Branch Operation Contact
- `getFormattedDistance($distanceData)` - Format distance data for display

#### `File` Model

- `getDistanceToBranch()` - Get raw distance data
- `getFormattedDistanceToBranch()` - Get formatted distance string

## Usage

### In Appointment Tables

The distance column automatically appears in:
- Main Appointment Resource table
- File Resource's Appointments Relation Manager

The column shows:
- **Distance (Car)**: Travel time in minutes (e.g., "15.5 min")
- **Description**: Shows the File's address for reference

### Example Output

```
Distance (Car): 15.5 min
From: 123 Main Street, City, Country
```

## Error Handling

- **No API Key**: Shows "N/A" and logs warning
- **Missing Addresses**: Returns null for empty addresses
- **API Errors**: Logs errors and returns null
- **Network Issues**: Handles exceptions gracefully

## Performance Considerations

- Calculations are performed on-demand (not cached)
- API calls are made only when viewing appointment tables
- No database storage of distance data
- Consider implementing caching for frequently accessed routes

## Testing

Run the unit tests:

```bash
php artisan test tests/Unit/DistanceCalculationTest.php
```

## Troubleshooting

### Common Issues

1. **"N/A" displayed**: Check if Google Maps API key is configured
2. **API errors**: Verify Distance Matrix API is enabled
3. **No distance shown**: Ensure both File and Provider Branch Operation Contact have addresses

### Debug Mode

Enable debug logging to see detailed API responses:

```php
// In DistanceCalculationService.php, uncomment or add logging
Log::info('Distance calculation response', $response->json());
```

## Accuracy

To improve accuracy, the service:

1. **Geocodes both addresses** using the Geocoding API so each address is resolved to exact coordinates.
2. **Calls Distance Matrix with coordinates** (`origins=lat,lng&destinations=lat,lng`) instead of raw address strings, so Google uses the intended locations rather than guessing (e.g. "Main Street" in the correct country).
3. **Uses an optional region** (`GOOGLE_MAPS_REGION`) to bias geocoding (e.g. Ireland) so short or ambiguous addresses resolve correctly.

If geocoding fails for either address, the service falls back to passing address strings to the Distance Matrix API (previous behaviour).

## Future Enhancements

- Add caching for distance calculations
- Support for different travel modes (walking, transit)
- Batch distance calculations for multiple appointments
- Distance-based appointment sorting/filtering