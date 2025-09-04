<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// WICHTIG: Die korrekte API URL ist .io, nicht .de!
// Falls .de in der Konfiguration steht, automatisch korrigieren
$apiUrl = $data['api_url'];
if (strpos($apiUrl, '.de') !== false) {
    $apiUrl = str_replace('.de', '.io', $apiUrl);
}

// FEHLER 1: Der Endpoint /info existiert nicht!
// Verwende stattdessen /contacts oder /articles zum Testen
$testEndpoint = '/contacts'; // Dieser Endpoint funktioniert nachweislich

$ch = curl_init($apiUrl . $testEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $data['api_key'],
    'Accept: application/json',
    'Content-Type: application/json' // FEHLER 2: Content-Type Header fehlte
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // FEHLER 3: Redirects folgen

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Detailliertere Fehlerbehandlung
if ($error) {
    echo json_encode([
        'success' => false,
        'error' => 'CURL Fehler: ' . $error
    ]);
    exit;
}

if ($httpCode === 200 || $httpCode === 201) {
    // Erfolgreich verbunden
    $responseData = json_decode($response, true);

    // Da /contacts keine Company-Info hat, zeige andere Info
    $companyName = 'Lexware API verbunden';

    // Optional: Versuche einen zweiten Call um mehr Info zu bekommen
    if (!empty($responseData)) {
        if (isset($responseData['content']) && count($responseData['content']) > 0) {
            $companyName = 'Lexware API - ' . count($responseData['content']) . ' Kontakte gefunden';
        }
    }

    echo json_encode([
        'success' => true,
        'company_name' => $companyName,
        'api_version' => 'lexware.io v1',
        'endpoint_tested' => $testEndpoint
    ]);
} elseif ($httpCode === 401) {
    echo json_encode([
        'success' => false,
        'error' => 'Authentifizierung fehlgeschlagen - API Key ungültig oder fehlt'
    ]);
} elseif ($httpCode === 404) {
    // Wenn 404, versuche alternativen Endpoint
    // Dies könnte bedeuten, dass die API URL falsch ist
    echo json_encode([
        'success' => false,
        'error' => 'API Endpoint nicht gefunden. Prüfen Sie die API URL (sollte https://api.lexware.io/v1 sein)'
    ]);
} elseif ($httpCode === 403) {
    echo json_encode([
        'success' => false,
        'error' => 'Zugriff verweigert - API Key hat keine Berechtigung für diesen Endpoint'
    ]);
} elseif ($httpCode === 429) {
    echo json_encode([
        'success' => false,
        'error' => 'Rate Limit erreicht - Zu viele Anfragen. Bitte später erneut versuchen'
    ]);
} else {
    // Andere Fehlercodes
    $errorMessage = 'Verbindung fehlgeschlagen (HTTP ' . $httpCode . ')';

    // Versuche mehr Details aus der Response zu bekommen
    if ($response) {
        $responseData = json_decode($response, true);
        if (isset($responseData['message'])) {
            $errorMessage .= ' - ' . $responseData['message'];
        } elseif (isset($responseData['error'])) {
            $errorMessage .= ' - ' . $responseData['error'];
        }
    }

    echo json_encode([
        'success' => false,
        'error' => $errorMessage
    ]);
}
