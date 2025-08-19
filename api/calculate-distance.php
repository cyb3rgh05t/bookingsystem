<?php

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $customerAddress = $data['address'] ?? '';

    if (empty($customerAddress)) {
        throw new Exception('Keine Adresse angegeben');
    }

    // Get company settings
    $db = Database::getInstance();
    $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

    $response = [
        'success' => true,
        'distance' => 0,
        'travelCost' => 0,
        'usingGoogleMaps' => false
    ];

    // Check if Google Maps API key is configured
    if (!empty($settings['google_maps_api_key'])) {
        // Use real Google Maps Distance Matrix API
        $companyAddress = $settings['address'];
        $apiKey = $settings['google_maps_api_key'];

        // Prepare addresses for API
        $origin = urlencode($companyAddress);
        $destination = urlencode($customerAddress);

        // Call Google Distance Matrix API
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?"
            . "origins={$origin}"
            . "&destinations={$destination}"
            . "&key={$apiKey}"
            . "&units=metric"
            . "&language=de"
            . "&mode=driving";

        // Use cURL to get the data
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $apiResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $apiData = json_decode($apiResponse, true);

            // Check if API returned valid results
            if (
                $apiData['status'] === 'OK' &&
                isset($apiData['rows'][0]['elements'][0]['status']) &&
                $apiData['rows'][0]['elements'][0]['status'] === 'OK'
            ) {

                // Get distance in kilometers
                $distanceInMeters = $apiData['rows'][0]['elements'][0]['distance']['value'];
                $distance = round($distanceInMeters / 1000, 1); // Convert to km

                // Get duration in minutes (optional, for information)
                $durationInSeconds = $apiData['rows'][0]['elements'][0]['duration']['value'];
                $duration = round($durationInSeconds / 60); // Convert to minutes

                $response['distance'] = $distance;
                $response['duration'] = $duration;
                $response['usingGoogleMaps'] = true;

                // Log successful API usage
                error_log("Google Maps API: Distance calculated - {$distance}km in {$duration} minutes");
            } else {
                // API couldn't calculate distance (e.g., no route found)
                error_log("Google Maps API Error: " . json_encode($apiData));

                // Fall back to simulation
                $distance = rand(5, 40);
                $response['distance'] = $distance;
                $response['error'] = 'Route konnte nicht berechnet werden';
            }
        } else {
            // API request failed
            error_log("Google Maps API HTTP Error: {$httpCode}");

            // Fall back to simulation
            $distance = rand(5, 40);
            $response['distance'] = $distance;
            $response['apiError'] = 'Google Maps API nicht erreichbar';
        }
    } else {
        // No API key configured - use simulation
        $distance = rand(5, 40); // Simulated distance in km
        $response['distance'] = $distance;
        $response['message'] = 'Demo-Modus: Zufällige Entfernung. Bitte Google Maps API Key in den Einstellungen eintragen.';
    }

    // Calculate travel cost based on distance
    if ($response['distance'] > $settings['free_distance_km']) {
        $chargeableDistance = $response['distance'] - $settings['free_distance_km'];
        $response['travelCost'] = round($chargeableDistance * $settings['price_per_km'], 2);
    }

    // Check if within service area
    if ($response['distance'] > $settings['max_distance']) {
        $response['success'] = false;
        $response['error'] = 'Außerhalb des Servicegebiets (max. ' . $settings['max_distance'] . 'km)';
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// ========================================
// ALTERNATIVE: Verwendung mit file_get_contents statt cURL
// ========================================
/*
function calculateDistanceAlternative($origin, $destination, $apiKey) {
$url = "https://maps.googleapis.com/maps/api/distancematrix/json?"
. "origins=" . urlencode($origin)
. "&destinations=" . urlencode($destination)
. "&key=" . $apiKey
. "&units=metric"
. "&language=de"
. "&mode=driving";

// Create context for file_get_contents with timeout
$context = stream_context_create([
'http' => [
'timeout' => 10,
'ignore_errors' => true
],
'ssl' => [
'verify_peer' => false,
'verify_peer_name' => false
]
]);

$response = @file_get_contents($url, false, $context);

if ($response !== false) {
return json_decode($response, true);
}

return false;
}
*/