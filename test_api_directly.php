<?php

// Direct API test script
$apiKey = 'AIzaSyDHfyz5Um1vsDuiBuZdT1lVpHqUMuSDRHc';
$origin = '1 Árd Álainn, Upper Fairhill, Cork, T23 VW08, Ireland';
$destination = '25 Earlwood Estate, Togher, Cork, T12 PP92, Ireland';

$url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . urlencode($origin) . "&destinations=" . urlencode($destination) . "&mode=driving&key=" . $apiKey;

echo "Testing Google Maps Distance Matrix API directly...\n";
echo "URL: " . $url . "\n\n";

$response = file_get_contents($url);
$data = json_decode($response, true);

echo "Response Status: " . ($data['status'] ?? 'unknown') . "\n";
echo "Full Response:\n";
print_r($data);

if (isset($data['rows'][0]['elements'][0])) {
    $element = $data['rows'][0]['elements'][0];
    echo "\nElement Status: " . ($element['status'] ?? 'unknown') . "\n";
    
    if ($element['status'] === 'OK') {
        echo "Distance: " . $element['distance']['text'] . "\n";
        echo "Duration: " . $element['duration']['text'] . "\n";
    } else {
        echo "Error: " . ($element['error_message'] ?? 'No error message') . "\n";
    }
} 