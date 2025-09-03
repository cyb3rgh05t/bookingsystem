<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Simuliere Verbindungstest
$ch = curl_init($data['api_url'] . '/info');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $data['api_key'],
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $info = json_decode($response, true);
    echo json_encode([
        'success' => true,
        'company_name' => $info['company']['name'] ?? 'Verbunden'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Verbindung fehlgeschlagen (HTTP ' . $httpCode . ')'
    ]);
}
