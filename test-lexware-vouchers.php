<?php

/**
 * Test für Lexware (ehemals lexoffice) API mit KORREKTER Struktur
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

$db = Database::getInstance();
$settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

$apiUrl = $settings['lexware_api_url'] ?? 'https://api.lexware.io/v1';
$apiKey = $settings['lexware_api_key'] ?? '';

echo "<!DOCTYPE html><html><head><title>Lexware API Test - Korrekte Struktur</title></head><body>";
echo "<h1>Lexware API Test mit korrekter Struktur</h1>";

echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin-bottom: 20px;'>";
echo "<strong>✅ API identifiziert:</strong> lexoffice API (rebranded zu lexware.io)<br>";
echo "Endpoint: <code>/invoices</code> (NICHT /vouchers!)<br>";
echo "Dokumentation: <a href='https://developers.lexware.io/docs/' target='_blank'>developers.lexware.io</a>";
echo "</div>";

// WICHTIG: Das korrekte Datumsformat mit Millisekunden!
echo "<div style='background: #ffe7e7; padding: 15px; border-left: 4px solid #f44336; margin-bottom: 20px;'>";
echo "<strong>⚠️ WICHTIG - Datumsformat:</strong><br>";
echo "Die API erwartet: <code>2025-09-04T00:00:00.000+01:00</code> (mit .000 Millisekunden!)<br>";
echo "NICHT: <code>2025-09-04T12:13:38+02:00</code> (ohne Millisekunden)";
echo "</div>";

// Test 1: Minimale Rechnung erstellen
echo "<h2>Test 1: Minimale Rechnung erstellen</h2>";

$minimalInvoice = [
    'archived' => false,
    'voucherDate' => '2025-09-04T00:00:00.000+01:00', // HARTKODIERTES korrektes Format!
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
            'name' => 'Test Service',
            'quantity' => 1,
            'unitName' => 'Stück',
            'unitPrice' => [
                'currency' => 'EUR',
                'netAmount' => 100.00,
                'taxRatePercentage' => 19
            ],
            'discountPercentage' => 0
        ]
    ],
    'totalPrice' => [
        'currency' => 'EUR'
    ],
    'taxConditions' => [
        'taxType' => 'net'
    ],
    'paymentConditions' => [
        'paymentTermLabel' => '14 Tage netto',
        'paymentTermDuration' => 14
    ],
    'title' => 'Rechnung',
    'introduction' => 'Test-Rechnung',
    'remark' => 'Dies ist eine Test-Rechnung'
];

echo "<pre>";
echo "Sende Rechnung:\n";
echo json_encode($minimalInvoice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$ch = curl_init($apiUrl . '/invoices');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($minimalInvoice));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "\nHTTP Status: $httpCode\n\n";

if ($error) {
    echo "❌ CURL Fehler: $error\n";
} elseif ($httpCode === 201 || $httpCode === 200) {
    echo "✅ ERFOLG! Rechnung wurde erstellt!\n\n";
    $created = json_decode($response, true);
    echo "Response:\n";
    echo json_encode($created, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (isset($created['id'])) {
        echo "\n\n📋 Rechnung erstellt mit ID: " . $created['id'] . "\n";
        echo "📄 Resource URI: " . ($created['resourceUri'] ?? 'N/A') . "\n";

        // Speichere für weitere Tests
        $_SESSION['last_invoice_id'] = $created['id'];
    }
} elseif ($httpCode === 400 || $httpCode === 422) {
    echo "❌ Validierungsfehler\n";
    $errorData = json_decode($response, true);
    echo "Fehlerdetails:\n";
    echo json_encode($errorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (isset($errorData['IssueList'])) {
        echo "\n⚠️ Probleme:\n";
        foreach ($errorData['IssueList'] as $issue) {
            echo "- " . ($issue['source'] ?? 'Unbekannt') . ": " . ($issue['i18nKey'] ?? $issue['type'] ?? 'Fehler') . "\n";
        }
    }
} elseif ($httpCode === 401) {
    echo "❌ Authentifizierung fehlgeschlagen - API Key prüfen!\n";
} elseif ($httpCode === 429) {
    echo "⚠️ Rate Limit erreicht - bitte später erneut versuchen\n";
} else {
    echo "⚠️ Unerwarteter Status\n";
    echo "Response: " . substr($response, 0, 1000) . "\n";
}
echo "</pre>";

// Test 2: Vollständige Rechnung mit allen Features
echo "<h2>Test 2: Vollständige Rechnung (wie im Buchungssystem)</h2>";

// Dynamisches Datum mit korrektem Format
$today = new DateTime();
$nextWeek = new DateTime('+7 days');

// Format: YYYY-MM-DDTHH:MM:SS.sss±HH:MM
$voucherDateFormatted = $today->format('Y-m-d\T00:00:00.000+01:00');
$shippingDateFormatted = $nextWeek->format('Y-m-d\T00:00:00.000+01:00');

$fullInvoice = [
    'archived' => false,
    'voucherDate' => $voucherDateFormatted, // Dynamisch aber korrektes Format
    'address' => [
        'name' => 'Max Mustermann',
        'street' => 'Musterstraße 42',
        'city' => 'Düsseldorf',
        'zip' => '40215',
        'countryCode' => 'DE'
    ],
    'lineItems' => [
        [
            'type' => 'custom',
            'name' => 'Innenreinigung',
            'description' => 'Komplette Innenreinigung des Fahrzeugs',
            'quantity' => 1,
            'unitName' => 'Stück',
            'unitPrice' => [
                'currency' => 'EUR',
                'netAmount' => 50.42, // 59.90 / 1.19
                'taxRatePercentage' => 19
            ],
            'discountPercentage' => 0
        ],
        [
            'type' => 'custom',
            'name' => 'Außenreinigung',
            'description' => 'Handwäsche und Politur',
            'quantity' => 1,
            'unitName' => 'Stück',
            'unitPrice' => [
                'currency' => 'EUR',
                'netAmount' => 42.02, // 49.90 / 1.19
                'taxRatePercentage' => 19
            ],
            'discountPercentage' => 0
        ],
        [
            'type' => 'custom',
            'name' => 'Anfahrtskosten',
            'description' => 'Entfernung: 15.5 km',
            'quantity' => 1,
            'unitName' => 'Pauschale',
            'unitPrice' => [
                'currency' => 'EUR',
                'netAmount' => 16.81, // 20.00 / 1.19
                'taxRatePercentage' => 19
            ],
            'discountPercentage' => 0
        ],
        [
            'type' => 'text',
            'name' => 'Fahrzeugdaten',
            'description' => 'Marke: BMW, Modell: 3er, Baujahr: 2020, Kennzeichen: D-AB-1234'
        ]
    ],
    'totalPrice' => [
        'currency' => 'EUR'
    ],
    'taxConditions' => [
        'taxType' => 'net'
    ],
    'paymentConditions' => [
        'paymentTermLabel' => '14 Tage netto',
        'paymentTermDuration' => 14
    ],
    'shippingConditions' => [
        'shippingDate' => $shippingDateFormatted,
        'shippingType' => 'service'
    ],
    'title' => 'Rechnung',
    'introduction' => 'Rechnung für Ihre Buchung ' . date('Y') . '-0001 vom ' . date('d.m.Y'),
    'remark' => "Vielen Dank für Ihren Auftrag!\n\nTermin: " . $nextWeek->format('d.m.Y') . " um 14:00 Uhr\nBuchungsnummer: " . date('Y') . '-0001'
];

echo "<pre>";
echo "Sende vollständige Rechnung:\n";
echo "voucherDate Format: " . $voucherDateFormatted . "\n";
echo "shippingDate Format: " . $shippingDateFormatted . "\n\n";
echo json_encode($fullInvoice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Warte wegen Rate Limit
sleep(2);

$ch = curl_init($apiUrl . '/invoices');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fullInvoice));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\nHTTP Status: $httpCode\n\n";

if ($httpCode === 201 || $httpCode === 200) {
    echo "✅ ERFOLG! Vollständige Rechnung erstellt!\n";
    $created = json_decode($response, true);
    echo "Response:\n";
    echo json_encode($created, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "❌ Fehler beim Erstellen\n";
    $errorData = json_decode($response, true);
    if ($errorData) {
        echo json_encode($errorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo "Response: " . substr($response, 0, 500);
    }
}
echo "</pre>";

// Helper-Funktion für korrektes Datumsformat
echo "<h2>Helper-Funktion für Datumsformat:</h2>";
echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "// PHP Helper-Funktion für Lexware Datumsformat:\n";
echo "function getLexwareDateFormat(\$date = null) {\n";
echo "    if (\$date === null) {\n";
echo "        \$date = new DateTime();\n";
echo "    } elseif (is_string(\$date)) {\n";
echo "        \$date = new DateTime(\$date);\n";
echo "    }\n";
echo "    // Format: YYYY-MM-DDTHH:MM:SS.sss+HH:MM\n";
echo "    // Beachte: .000 für Millisekunden ist PFLICHT!\n";
echo "    return \$date->format('Y-m-d\\T00:00:00.000+01:00');\n";
echo "}\n\n";
echo "// Beispiele:\n";
echo "getLexwareDateFormat(); // Heute\n";
echo "getLexwareDateFormat('2025-09-11'); // Spezifisches Datum\n";
echo "getLexwareDateFormat('+7 days'); // In 7 Tagen\n";
echo "</pre>";

// Zusammenfassung
echo "<h2>Integration Status:</h2>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>";
echo "<h3>✅ Gelöste Probleme:</h3>";
echo "<ul>";
echo "<li>API identifiziert: lexoffice (jetzt lexware.io)</li>";
echo "<li>Korrekter Endpoint: <code>/invoices</code></li>";
echo "<li>Korrektes Datumsformat: <code>YYYY-MM-DDTHH:MM:SS.000+HH:MM</code></li>";
echo "<li>Netto-Beträge werden berechnet</li>";
echo "</ul>";

echo "<h3>📋 Das korrekte Datumsformat:</h3>";
echo "<code style='background: #fff; padding: 10px; display: block; margin: 10px 0;'>";
echo "2025-09-04T00:00:00.000+01:00";
echo "</code>";
echo "<p><strong>Wichtig:</strong> Die <code>.000</code> Millisekunden sind PFLICHT!</p>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-top: 20px;'>";
echo "<strong>⚠️ Rate Limit:</strong> Die API erlaubt nur wenige Requests pro Minute. In der Produktion sollten Requests verzögert werden.";
echo "</div>";

echo "</body></html>";
