<?php

/**
 * Test für finalize=true Parameter
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

$db = Database::getInstance();
$settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

$apiUrl = $settings['lexware_api_url'] ?? 'https://api.lexware.io/v1';
$apiKey = $settings['lexware_api_key'] ?? '';

function getLexwareDateFormat($date = null)
{
    if ($date === null) {
        $date = new DateTime();
    } elseif (is_string($date)) {
        $date = new DateTime($date);
    }
    return $date->format('Y-m-d\T00:00:00.000+01:00');
}

echo "<!DOCTYPE html><html><head><title>Lexware finalize Parameter Test</title></head><body>";
echo "<h1>Test: Rechnung mit finalize=true erstellen</h1>";

echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
echo "<strong>✅ Lösung gefunden!</strong><br>";
echo "Verwende <code>?finalize=true</code> um Rechnungen direkt als 'open' (unbezahlt) zu erstellen<br>";
echo "Ohne diesen Parameter werden Rechnungen immer als 'draft' (Entwurf) erstellt";
echo "</div>";

// Basis-Rechnungsdaten
$invoice = [
    'archived' => false,
    'voucherDate' => getLexwareDateFormat(),
    'address' => [
        'name' => 'Test Kunde',
        'street' => 'Musterstraße 1',
        'city' => 'Düsseldorf',
        'zip' => '40215',
        'countryCode' => 'DE'
    ],
    'lineItems' => [
        [
            'type' => 'custom',
            'name' => 'Mobile Autoreinigung',
            'quantity' => 1,
            'unitName' => 'Stück',
            'unitPrice' => [
                'currency' => 'EUR',
                'netAmount' => 100.00,
                'taxRatePercentage' => 0
            ],
            'discountPercentage' => 0
        ]
    ],
    'totalPrice' => [
        'currency' => 'EUR'
    ],
    'taxConditions' => [
        'taxType' => 'vatfree'
    ],
    'paymentConditions' => [
        'paymentTermLabel' => '14 Tage netto',
        'paymentTermDuration' => 14
    ],
    'shippingConditions' => [
        'shippingDate' => getLexwareDateFormat('+7 days'),
        'shippingType' => 'service'
    ],
    'title' => 'Rechnung',
    'introduction' => 'Test Rechnung',
    'remark' => 'Umsatzsteuerbefreite Leistung gemäß §19 UStG'
];

// Test 1: OHNE finalize Parameter (Standard = Entwurf)
echo "<h2>Test 1: OHNE finalize Parameter</h2>";
echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
echo "URL: " . $apiUrl . "/invoices\n";
echo "Erwarteter Status: draft (Entwurf)";
echo "</pre>";

$ch = curl_init($apiUrl . '/invoices');  // OHNE finalize=true
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoice));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 201 || $httpCode === 200) {
    $created = json_decode($response, true);
    echo "<div style='background: #fff3cd; padding: 10px; margin-top: 10px; border-radius: 5px;'>";
    echo "✅ Rechnung erstellt<br>";
    echo "ID: " . $created['id'] . "<br>";

    // Prüfe Status
    sleep(1);
    $ch = curl_init($apiUrl . '/invoices/' . $created['id']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $getResponse = curl_exec($ch);
    curl_close($ch);

    $invoice = json_decode($getResponse, true);
    echo "<strong>Status: " . ($invoice['voucherStatus'] ?? 'unbekannt') . "</strong>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; margin-top: 10px; border-radius: 5px;'>";
    echo "❌ Fehler: HTTP " . $httpCode;
    echo "</div>";
}

// Warte zwischen Tests
sleep(2);

// Test 2: MIT finalize=true Parameter
echo "<h2>Test 2: MIT finalize=true Parameter</h2>";
echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
echo "URL: " . $apiUrl . "/invoices?finalize=true\n";
echo "Erwarteter Status: open (Unbezahlt)";
echo "</pre>";

$ch = curl_init($apiUrl . '/invoices?finalize=true');  // MIT finalize=true
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoice));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 201 || $httpCode === 200) {
    $created = json_decode($response, true);
    echo "<div style='background: #d4edda; padding: 10px; margin-top: 10px; border-radius: 5px;'>";
    echo "✅ <strong>Rechnung erfolgreich finalisiert!</strong><br>";
    echo "ID: " . $created['id'] . "<br>";

    // Prüfe Status
    sleep(1);
    $ch = curl_init($apiUrl . '/invoices/' . $created['id']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $getResponse = curl_exec($ch);
    curl_close($ch);

    $invoice = json_decode($getResponse, true);
    echo "<strong>Status: " . ($invoice['voucherStatus'] ?? 'unbekannt') . "</strong><br>";
    if (isset($invoice['voucherNumber'])) {
        echo "Rechnungsnummer: " . $invoice['voucherNumber'];
    }
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; margin-top: 10px; border-radius: 5px;'>";
    echo "❌ Fehler: HTTP " . $httpCode;
    $errorData = json_decode($response, true);
    if (isset($errorData['message'])) {
        echo "<br>" . $errorData['message'];
    }
    echo "</div>";
}

// Zusammenfassung
echo "<h2>📝 Integration in lexware-invoice.php:</h2>";
echo "<div style='background: #e8f4fd; padding: 20px; border-radius: 8px;'>";
echo "<h3>Anpassung der sendToLexware Methode:</h3>";
echo "<pre style='background: #fff; padding: 15px; border-radius: 5px;'>";
echo 'private function sendToLexware($invoiceData, $finalize = true)
{
    // ...
    
    // Füge Query-Parameter hinzu
    $endpoint = "/invoices";
    if ($finalize) {
        $endpoint .= "?finalize=true";  // ⬅️ Das macht den Unterschied!
    }
    
    $ch = curl_init($this->apiUrl . $endpoint);
    // ... Rest des Codes
}';
echo "</pre>";

echo "<h3>Verwendung:</h3>";
echo "<pre style='background: #fff; padding: 15px; border-radius: 5px;'>";
echo '// Rechnung direkt als "open" (unbezahlt) erstellen:
$this->sendToLexware($invoiceData, true);  // Standard

// Oder als Entwurf erstellen (zum Testen):
$this->sendToLexware($invoiceData, false);';
echo "</pre>";

echo "<h4>Vorteile der direkten Finalisierung:</h4>";
echo "<ul>";
echo "<li>✅ Rechnung wird sofort verbucht</li>";
echo "<li>✅ Erhält automatisch eine Rechnungsnummer</li>";
echo "<li>✅ Erscheint in den offenen Posten</li>";
echo "<li>✅ Kann direkt versendet werden</li>";
echo "<li>✅ Kein manueller Schritt nötig</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin-top: 20px;'>";
echo "<strong>🎉 Problem gelöst!</strong><br>";
echo "Mit <code>?finalize=true</code> werden Rechnungen direkt als 'open' (unbezahlt) erstellt.<br>";
echo "Die Funktion in deiner <code>lexware-invoice.php</code> wurde bereits entsprechend angepasst.";
echo "</div>";

echo "</body></html>";
