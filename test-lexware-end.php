<?php

/**
 * Erweiterte Endpoint-Suche für Lexware API
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

$db = Database::getInstance();
$settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

$apiUrl = $settings['lexware_api_url'] ?? 'https://api.lexware.io/v1';
$apiKey = $settings['lexware_api_key'] ?? '';

echo "<!DOCTYPE html><html><head><title>Lexware Endpoint Test</title></head><body>";
echo "<h1>Lexware API - Endpoint Suche</h1>";

// Da articles funktioniert hat, versuchen wir ähnliche Endpoints
$testEndpoints = [
    // Mögliche Rechnungs-Endpoints
    '/vouchers' => 'Belege (Alternative für Rechnungen)',
    '/invoices' => 'Rechnungen (Original)',
    '/invoice' => 'Rechnung (Singular)',
    '/sales-invoices' => 'Verkaufsrechnungen',
    '/documents' => 'Dokumente',
    '/billing' => 'Abrechnung',

    // Mögliche Kunden-Endpoints
    '/customers' => 'Kunden (Original)',
    '/customer' => 'Kunde (Singular)',
    '/contacts' => 'Kontakte',
    '/business-partners' => 'Geschäftspartner',
    '/clients' => 'Klienten',

    // Andere wichtige Endpoints
    '/articles' => 'Artikel (funktioniert bereits)',
    '/orders' => 'Aufträge',
    '/quotes' => 'Angebote',
    '/deliveries' => 'Lieferungen',
    '/payments' => 'Zahlungen',
    '/accounts' => 'Konten',
    '/taxes' => 'Steuern',
    '/vat' => 'Umsatzsteuer'
];

echo "<h2>Teste alle möglichen Endpoints:</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Endpoint</th><th>Beschreibung</th><th>Status</th><th>Details</th></tr>";

$workingEndpoints = [];

foreach ($testEndpoints as $endpoint => $description) {
    $testUrl = $apiUrl . $endpoint;

    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $statusColor = ($httpCode === 200 || $httpCode === 201) ? 'green' : 'red';
    $statusText = ($httpCode === 200 || $httpCode === 201) ? '✅ OK' : '❌ ' . $httpCode;

    echo "<tr>";
    echo "<td><code>$endpoint</code></td>";
    echo "<td>$description</td>";
    echo "<td style='color: $statusColor;'>$statusText</td>";
    echo "<td>";

    if ($httpCode === 200 || $httpCode === 201) {
        $workingEndpoints[] = $endpoint;
        $data = json_decode($response, true);
        if ($data) {
            $keys = array_keys($data);
            echo "Felder: " . implode(", ", array_slice($keys, 0, 5));
            if (count($keys) > 5) {
                echo "...";
            }
        }
    } elseif ($httpCode === 405) {
        echo "Method Not Allowed - möglicherweise nur POST/PUT";
    } elseif ($httpCode === 401) {
        echo "Nicht autorisiert";
    } elseif ($httpCode === 403) {
        echo "Keine Berechtigung";
    } elseif ($httpCode === 404) {
        echo "Nicht gefunden";
    } else {
        echo "Fehler";
    }

    echo "</td>";
    echo "</tr>";
}

echo "</table>";

// Zusammenfassung
echo "<h2>Zusammenfassung:</h2>";
if (count($workingEndpoints) > 0) {
    echo "<div style='background: #dfd; padding: 10px; border: 1px solid #0a0;'>";
    echo "<strong>✅ Funktionierende Endpoints:</strong><br>";
    echo "<ul>";
    foreach ($workingEndpoints as $ep) {
        echo "<li><code>$ep</code></li>";
    }
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #fdd; padding: 10px; border: 1px solid #a00;'>";
    echo "<strong>❌ Keine funktionierenden Endpoints gefunden!</strong><br>";
    echo "Möglicherweise ist die API-URL falsch oder es wird eine andere Authentifizierung benötigt.";
    echo "</div>";
}

// Test POST Request auf articles (um zu sehen ob CREATE funktioniert)
echo "<h2>Test: POST Request</h2>";
echo "<p>Teste ob wir Daten erstellen können (am Beispiel Articles):</p>";

$ch = curl_init($apiUrl . '/articles');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Accept: application/json',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'test' => 'test'  // Dummy data
]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<pre>";
echo "POST /articles HTTP Code: $httpCode\n";
if ($httpCode === 400 || $httpCode === 422) {
    echo "Validierungsfehler (erwartet) - API akzeptiert POST\n";
    $error = json_decode($response, true);
    if ($error) {
        echo "Fehlerdetails: " . json_encode($error, JSON_PRETTY_PRINT) . "\n";
        echo "\n⚠️ Diese Fehlermeldung zeigt uns, welche Felder benötigt werden!\n";
    }
} elseif ($httpCode === 405) {
    echo "POST nicht erlaubt auf diesem Endpoint\n";
} elseif ($httpCode === 201) {
    echo "✅ Erfolgreich erstellt (unwahrscheinlich mit Dummy-Daten)\n";
}
echo "</pre>";

// Empfehlungen basierend auf Ergebnissen
echo "<h2>Empfehlungen für die Implementierung:</h2>";
echo "<ol>";
if (in_array('/vouchers', $workingEndpoints)) {
    echo "<li>Verwende <code>/vouchers</code> statt <code>/invoices</code> für Rechnungen</li>";
}
if (in_array('/contacts', $workingEndpoints)) {
    echo "<li>Verwende <code>/contacts</code> statt <code>/customers</code> für Kunden</li>";
}
echo "<li>Prüfe die <a href='https://developers.lexware.io/docs/' target='_blank'>Lexware API Dokumentation</a> für die exakte Struktur</li>";
echo "<li>Die API verwendet Pagination (siehe articles Response)</li>";
echo "<li>Implementiere Error-Handling für verschiedene HTTP Status Codes</li>";
echo "</ol>";

echo "<h2>Nächste Schritte:</h2>";
echo '<a href="test-lexware-api.php" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;">← Zurück zum Haupttest</a> ';
echo '<a href="admin/settings.php" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;">→ Einstellungen</a>';

echo "</body></html>";
