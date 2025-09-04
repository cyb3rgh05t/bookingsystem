<?php

/**
 * API Discovery - Finde die richtige API-Struktur
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

$db = Database::getInstance();
$settings = $db->fetch("SELECT * FROM settings WHERE id = 1");
$apiKey = $settings['lexware_api_key'] ?? '';

echo "<!DOCTYPE html><html><head><title>Lexware API Discovery</title></head><body>";
echo "<h1>Lexware API Discovery</h1>";

// Test verschiedene Base URLs
echo "<h2>1. Teste verschiedene API Base URLs:</h2>";

$baseUrls = [
    'https://api.lexware.io/v1',
    'https://api.lexware.io/v2',
    'https://api.lexware.io',
    'https://api.lexoffice.io/v1',  // Alternative: lexoffice statt lexware
    'https://public-api.lexoffice.io/v1'
];

echo "<table border='1'>";
echo "<tr><th>Base URL</th><th>Test Endpoint</th><th>Status</th><th>Details</th></tr>";

foreach ($baseUrls as $baseUrl) {
    // Teste /contacts da wir wissen dass es funktioniert
    $testUrl = $baseUrl . '/contacts';

    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    echo "<tr>";
    echo "<td><code>$baseUrl</code></td>";
    echo "<td>/contacts</td>";
    echo "<td>" . ($httpCode === 200 ? "✅ $httpCode" : "❌ $httpCode") . "</td>";
    echo "<td>";
    if ($httpCode === 200) {
        echo "Funktioniert!";
        if ($effectiveUrl !== $testUrl) {
            echo " (Redirect zu: $effectiveUrl)";
        }
    } elseif ($httpCode === 301 || $httpCode === 302) {
        echo "Redirect zu: $effectiveUrl";
    }
    echo "</td>";
    echo "</tr>";
}
echo "</table>";

// Test mit funktionierender URL für invoices
echo "<h2>2. Teste /invoices mit funktionierender Base URL:</h2>";

// Nehme die URL die für contacts funktioniert hat
$workingUrl = 'https://api.lexware.io/v1';  // oder was auch immer oben funktioniert hat

$invoiceEndpoints = [
    '/invoices',
    '/invoice',
    '/vouchers',
    '/voucherlist/salesinvoices',
    '/documents/invoices'
];

echo "<table border='1'>";
echo "<tr><th>Endpoint</th><th>GET Status</th><th>POST Status</th><th>Details</th></tr>";

foreach ($invoiceEndpoints as $endpoint) {
    echo "<tr>";
    echo "<td><code>$endpoint</code></td>";

    // GET Test
    $ch = curl_init($workingUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $getCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "<td>" . ($getCode === 200 ? "✅ $getCode" : ($getCode === 400 ? "⚠️ $getCode" : "❌ $getCode")) . "</td>";

    // POST Test mit salesinvoice
    sleep(1); // Rate limit

    $testData = [
        'type' => 'salesinvoice',
        'taxType' => 'gross',
        'voucherNumber' => 'API-TEST-' . time(),
        'voucherDate' => date('Y-m-d')
    ];

    $ch = curl_init($workingUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $postCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "<td>" . ($postCode === 201 ? "✅ $postCode" : ($postCode === 406 ? "⚠️ $postCode" : "❌ $postCode")) . "</td>";

    echo "<td>";
    if ($getCode === 400) {
        $error = json_decode($response, true);
        if (isset($error['IssueList'])) {
            echo "Benötigt: " . $error['IssueList'][0]['source'];
        }
    }
    if ($postCode === 406) {
        echo "Type akzeptiert, andere Felder fehlen";
    }
    echo "</td>";
    echo "</tr>";
}
echo "</table>";

// Prüfe ob es lexoffice statt lexware ist
echo "<h2>3. Ist es vielleicht lexoffice statt lexware?</h2>";

$ch = curl_init('https://public-api.lexoffice.io/v1/invoices');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<pre>";
if ($httpCode === 401) {
    echo "✅ lexoffice API antwortet! (401 = Auth benötigt)\n";
    echo "Dies könnte die richtige API sein!\n";
    echo "URL: https://public-api.lexoffice.io/v1\n";
} elseif ($httpCode === 200) {
    echo "✅ lexoffice API funktioniert mit diesem Key!\n";
} else {
    echo "❌ lexoffice API nicht erreichbar oder falscher Key\n";
}
echo "</pre>";

// Zusammenfassung
echo "<h2>Zusammenfassung:</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;'>";
echo "<p><strong>Mögliche Probleme:</strong></p>";
echo "<ol>";
echo "<li><strong>Verwechslung lexware vs lexoffice:</strong> Die APIs sind unterschiedlich!</li>";
echo "<li><strong>API Version:</strong> Möglicherweise nutzt du v1 aber die Doku ist für v2</li>";
echo "<li><strong>Falsche Dokumentation:</strong> Du schaust dir die falsche API-Doku an</li>";
echo "</ol>";
echo "</div>";

echo "<h2>Empfehlung:</h2>";
echo "<div style='background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8;'>";
echo "<p><strong>Prüfe bitte:</strong></p>";
echo "<ol>";
echo "<li>Ist dein API Key für <strong>lexware</strong> oder <strong>lexoffice</strong>?</li>";
echo "<li>In welchem System hast du den API Key erstellt?</li>";
echo "<li>Die Dokumentation die du verlinkt hast (developers.lexware.io) - ist das die richtige für deine API?</li>";
echo "</ol>";
echo "<p>lexoffice ist die Cloud-Buchhaltung, lexware ist die Desktop-Software. Die APIs sind NICHT kompatibel!</p>";
echo "</div>";

echo "</body></html>";
