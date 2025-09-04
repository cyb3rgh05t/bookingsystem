<?php

/**
 * Test-Script für Lexware API Verbindung
 * Datei speichern als: test-lexware-api.php im Root-Verzeichnis
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

$db = Database::getInstance();
$settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

echo "<!DOCTYPE html><html><head><title>Lexware API Test</title></head><body>";
echo "<h1>Lexware API Verbindungstest</h1>";

// Zeige aktuelle Einstellungen (verstecke API Key teilweise)
echo "<h2>Aktuelle Einstellungen:</h2>";
echo "<pre>";
echo "API URL: " . ($settings['lexware_api_url'] ?? 'NICHT GESETZT') . "\n";
$apiKeyDisplay = !empty($settings['lexware_api_key'])
  ? substr($settings['lexware_api_key'], 0, 10) . '...'
  : 'NICHT GESETZT';
echo "API Key: " . $apiKeyDisplay . "\n";
echo "</pre>";

// Prüfe die richtige URL
if (strpos($settings['lexware_api_url'] ?? '', '.de') !== false) {
  echo '<div style="background: #fee; padding: 10px; border: 1px solid #f00; margin: 10px 0;">';
  echo '<strong>⚠️ WARNUNG:</strong> Die API URL verwendet .de statt .io!<br>';
  echo 'Richtige URL sollte sein: <code>https://api.lexware.io/v1</code>';
  echo '</div>';
}

// Test 1: Basis-Verbindungstest
echo "<h2>Test 1: API Erreichbarkeit</h2>";
$apiUrl = $settings['lexware_api_url'] ?? 'https://api.lexware.io/v1';
$apiKey = $settings['lexware_api_key'] ?? '';

if (empty($apiKey)) {
  echo '<div style="background: #fee; padding: 10px; border: 1px solid #f00;">';
  echo 'FEHLER: Kein API Key konfiguriert!';
  echo '</div>';
} else {
  // Teste verschiedene Endpoints
  $endpoints = [
    '/invoices' => 'Rechnungen',
    '/customers' => 'Kunden',
    '/articles' => 'Artikel'
  ];

  foreach ($endpoints as $endpoint => $name) {
    echo "<h3>Teste Endpoint: " . $name . " (" . $endpoint . ")</h3>";

    $ch = curl_init($apiUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . $apiKey,
      'Content-Type: application/json',
      'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Nur GET Request zum Testen
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "<pre>";
    if ($error) {
      echo "❌ CURL Fehler: " . $error . "\n";
    } else {
      echo "HTTP Status Code: " . $httpCode . "\n";

      if ($httpCode === 200 || $httpCode === 201) {
        echo "✅ Verbindung erfolgreich!\n";
        $data = json_decode($response, true);
        if ($data) {
          echo "Antwort-Struktur: " . json_encode(array_keys($data), JSON_PRETTY_PRINT) . "\n";
        }
      } elseif ($httpCode === 401) {
        echo "❌ Authentifizierung fehlgeschlagen - API Key ungültig oder fehlt\n";
        echo "Mögliche Lösungen:\n";
        echo "1. Prüfen Sie den API Key\n";
        echo "2. Der Key muss im Format 'Bearer {key}' gesendet werden\n";
        echo "3. Eventuell OAuth2 Token nötig?\n";
      } elseif ($httpCode === 404) {
        echo "❌ Endpoint nicht gefunden\n";
        echo "Mögliche Lösungen:\n";
        echo "1. API URL prüfen (sollte .io sein, nicht .de)\n";
        echo "2. Endpoint-Pfad prüfen\n";
        echo "3. Aktuelle URL: " . $apiUrl . $endpoint . "\n";
      } elseif ($httpCode === 403) {
        echo "❌ Zugriff verweigert - Keine Berechtigung\n";
        echo "Der API Key hat keine Berechtigung für diesen Endpoint\n";
      } else {
        echo "⚠️ Unerwarteter Status: " . $httpCode . "\n";
        echo "Response (erste 500 Zeichen):\n";
        echo substr($response, 0, 500) . "\n";
      }
    }
    echo "</pre>";
  }
}

// Test 2: Prüfe ob OAuth2 benötigt wird
echo "<h2>Test 2: Authentifizierungs-Methode</h2>";
echo "<pre>";
echo "Laut Lexware Dokumentation:\n";
echo "- API Key als Bearer Token: Authorization: Bearer {api_key}\n";
echo "- Oder OAuth2 Flow für User-spezifische Daten\n";
echo "\nAktuelle Implementierung verwendet: Bearer Token\n";
echo "</pre>";

// Test 3: Alternative API Endpoints
echo "<h2>Test 3: Alternative Endpoints testen</h2>";
$altEndpoints = [
  '' => 'Root / API Info',
  '/auth' => 'Authentifizierung',
  '/status' => 'API Status'
];

foreach ($altEndpoints as $endpoint => $name) {
  echo "<h3>Teste: " . $name . "</h3>";
  $testUrl = $apiUrl . $endpoint;

  $ch = curl_init($testUrl);
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
  echo "URL: " . $testUrl . "\n";
  echo "HTTP Code: " . $httpCode . "\n";
  if ($httpCode === 200) {
    echo "✅ Endpoint erreichbar\n";
    $responseData = json_decode($response, true);
    if ($responseData) {
      echo "Response Keys: " . implode(", ", array_keys($responseData)) . "\n";
    }
  }
  echo "</pre>";
}

// Empfehlungen
echo "<h2>Empfehlungen:</h2>";
echo "<ol>";
echo "<li>Stellen Sie sicher, dass die API URL auf <code>https://api.lexware.io/v1</code> gesetzt ist</li>";
echo "<li>Prüfen Sie, ob Ihr API Key gültig ist</li>";
echo "<li>Überprüfen Sie in der Lexware-Dokumentation die richtigen Endpoints</li>";
echo "<li>Möglicherweise müssen Sie zuerst einen OAuth2 Token generieren</li>";
echo "</ol>";

// Links
echo "<h2>Nächste Schritte:</h2>";
echo '<a href="admin/settings.php" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">→ Zu den Einstellungen</a> ';
echo '<a href="https://developers.lexware.io/docs/" target="_blank" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;">→ Lexware Dokumentation</a>';

echo "</body></html>";
