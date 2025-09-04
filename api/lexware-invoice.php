<?php

/**
 * Lexware (ehemals lexoffice) API Integration für automatische Rechnungserstellung
 * Verwendet die korrekte API-Struktur nach dem Rebranding
 */

require_once '../includes/config.php';
require_once '../includes/db.php';

class LexwareAPI
{
    private $apiUrl;
    private $apiKey;
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $settings = $this->db->fetch("SELECT * FROM settings WHERE id = 1");

        // Lexware API (ehemals lexoffice) - neue URL seit Mai 2025
        $this->apiUrl = $settings['lexware_api_url'] ?? 'https://api.lexware.io/v1';
        $this->apiKey = $settings['lexware_api_key'] ?? '';
    }

    /**
     * Erstellt eine Rechnung in Lexware
     */
    public function createInvoice($appointmentId, $paymentData = [])
    {
        try {
            // Hole Buchungsdaten aus der Datenbank
            $appointment = $this->getAppointmentData($appointmentId);

            if (!$appointment) {
                throw new Exception('Buchung nicht gefunden');
            }

            // Bereite Rechnungsdaten für Lexware API vor (NEUE STRUKTUR!)
            $invoiceData = $this->prepareInvoiceData($appointment, $paymentData);

            // Sende Rechnung an Lexware API
            $lexwareResponse = $this->sendToLexware($invoiceData);

            // Speichere Lexware ID in der Datenbank
            if ($lexwareResponse['success']) {
                $this->updateAppointmentWithInvoice($appointmentId, $lexwareResponse['invoice_id']);
            }

            return $lexwareResponse;
        } catch (Exception $e) {
            error_log("Lexware API Fehler: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Hole vollständige Buchungsdaten
     */
    private function getAppointmentData($appointmentId)
    {
        $query = "
            SELECT 
                a.*,
                c.first_name,
                c.last_name,
                c.email,
                c.phone,
                c.address,
                c.car_brand,
                c.car_model,
                c.car_year,
                c.license_plate
            FROM appointments a
            JOIN customers c ON a.customer_id = c.id
            WHERE a.id = ?
        ";

        $appointment = $this->db->fetch($query, [$appointmentId]);

        if ($appointment) {
            // Hole auch die gebuchten Services
            $services = $this->db->fetchAll("
                SELECT 
                    s.name,
                    s.description,
                    aps.price,
                    s.duration_minutes
                FROM appointment_services aps
                JOIN services s ON aps.service_id = s.id
                WHERE aps.appointment_id = ?
            ", [$appointmentId]);

            $appointment['services'] = $services;
        }

        return $appointment;
    }

    /**
     * Bereite Rechnungsdaten für Lexware API vor - FÜR UMSATZSTEUERBEFREITE UNTERNEHMEN!
     * Verwendet distanceSales als taxSubType für mobile Dienstleistungen
     */
    private function prepareInvoiceData($appointment, $paymentData = [])
    {
        // Generiere Buchungsnummer
        $bookingNumber = date('Y') . '-' . str_pad($appointment['id'], 4, '0', STR_PAD_LEFT);

        // Bereite Line Items vor
        $lineItems = [];

        // Services als Line Items - OHNE STEUER (0%)!
        foreach ($appointment['services'] as $service) {
            $lineItems[] = [
                'type' => 'custom',
                'name' => $service['name'],
                'description' => $service['description'],
                'quantity' => 1,
                'unitName' => 'Stück',
                'unitPrice' => [
                    'currency' => 'EUR',
                    'netAmount' => $service['price'], // Preis direkt verwenden (ohne MwSt-Berechnung)
                    'taxRatePercentage' => 0  // 0% Steuer für umsatzsteuerbefreite Unternehmen!
                ],
                'discountPercentage' => 0
            ];
        }

        // Anfahrtskosten als Line Item
        if ($appointment['travel_cost'] > 0) {
            $lineItems[] = [
                'type' => 'custom',
                'name' => 'Anfahrtskosten',
                'description' => sprintf('Entfernung: %.1f km', $appointment['distance_km']),
                'quantity' => 1,
                'unitName' => 'Pauschale',
                'unitPrice' => [
                    'currency' => 'EUR',
                    'netAmount' => $appointment['travel_cost'], // Preis direkt verwenden
                    'taxRatePercentage' => 0  // 0% Steuer
                ],
                'discountPercentage' => 0
            ];
        }

        // Zusätzlicher Text-Eintrag mit Fahrzeugdaten
        if (!empty($appointment['car_brand'])) {
            $lineItems[] = [
                'type' => 'text',
                'name' => 'Fahrzeugdaten',
                'description' => sprintf(
                    "Marke: %s, Modell: %s, Baujahr: %s, Kennzeichen: %s",
                    $appointment['car_brand'] ?? '-',
                    $appointment['car_model'] ?? '-',
                    $appointment['car_year'] ?? '-',
                    $appointment['license_plate'] ?? '-'
                )
            ];
        }

        // Erstelle die Rechnung nach Lexware API Spezifikation
        $invoice = [
            'archived' => false,
            'voucherStatus' => 'open',  // 'open' = unbezahlt, 'draft' = Entwurf, 'paid' = bezahlt
            'voucherDate' => $this->getLexwareDateFormat(), // Verwende Helper-Funktion für korrektes Format

            // Adresse
            'address' => [
                'name' => $appointment['first_name'] . ' ' . $appointment['last_name'],
                'street' => $this->extractStreet($appointment['address']),
                'city' => $this->extractCity($appointment['address']),
                'zip' => $this->extractZip($appointment['address']),
                'countryCode' => 'DE'
            ],

            // Rechnungspositionen
            'lineItems' => $lineItems,

            // Gesamtpreis
            'totalPrice' => [
                'currency' => 'EUR'
            ],

            // Steuerbedingungen - FÜR UMSATZSTEUERBEFREITE UNTERNEHMEN!
            'taxConditions' => [
                'taxType' => 'vatfree'  // vatfree für steuerbefreite Unternehmen
                // KEIN taxSubType nötig bei vatfree!
            ],

            // Zahlungsbedingungen
            'paymentConditions' => [
                'paymentTermLabel' => '14 Tage netto',
                'paymentTermDuration' => 14
            ],

            // Lieferbedingungen (Termin)
            'shippingConditions' => [
                'shippingDate' => $this->getLexwareDateFormat($appointment['appointment_date']),
                'shippingType' => 'service'
            ],

            // Texte
            'title' => 'Rechnung',
            'introduction' => sprintf(
                'Rechnung für Ihre Buchung %s vom %s',
                $bookingNumber,
                date('d.m.Y')
            ),

            // WICHTIG: Hinweis auf Steuerbefreiung im Remark!
            'remark' => sprintf(
                "Vielen Dank für Ihren Auftrag!\n\n" .
                    "Termin: %s um %s Uhr\n" .
                    "Buchungsnummer: %s\n\n" .
                    "Hinweis: Gemäß §19 UStG wird keine Umsatzsteuer berechnet.",
                date('d.m.Y', strtotime($appointment['appointment_date'])),
                $appointment['appointment_time'],
                $bookingNumber
            )
        ];

        return $invoice;
    }

    /**
     * Helper-Funktion für korrektes Lexware Datumsformat
     * WICHTIG: Die .000 Millisekunden sind PFLICHT!
     */
    private function getLexwareDateFormat($date = null)
    {
        if ($date === null) {
            $date = new DateTime();
        } elseif (is_string($date)) {
            $date = new DateTime($date);
        }

        // Format: YYYY-MM-DDTHH:MM:SS.sss±HH:MM
        // Die .000 für Millisekunden ist PFLICHT!
        return $date->format('Y-m-d\T00:00:00.000+01:00');
    }

    /**
     * Sende Rechnung an Lexware API
     * @param array $invoiceData Die Rechnungsdaten
     * @param bool $finalize Ob die Rechnung direkt finalisiert werden soll (true = open, false = draft)
     */
    private function sendToLexware($invoiceData, $finalize = true)
    {
        if (empty($this->apiKey)) {
            throw new Exception('Lexware API Key nicht konfiguriert');
        }

        // Füge Query-Parameter hinzu wenn Rechnung finalisiert werden soll
        $endpoint = '/invoices';
        if ($finalize) {
            $endpoint .= '?finalize=true';  // Macht die Rechnung direkt zu "open" statt "draft"
        }

        // API Request vorbereiten
        $ch = curl_init($this->apiUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoiceData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Request ausführen
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Debug-Logging
        if ($httpCode !== 201 && $httpCode !== 200) {
            error_log("Lexware API Request: " . json_encode($invoiceData));
            error_log("Lexware API Response ($httpCode): " . $response);
        }

        // Response verarbeiten
        if ($httpCode === 201 || $httpCode === 200) {
            $responseData = json_decode($response, true);

            return [
                'success' => true,
                'invoice_id' => $responseData['id'] ?? null,
                'invoice_number' => $responseData['voucherNumber'] ?? null,
                'resource_uri' => $responseData['resourceUri'] ?? null,
                'status' => $finalize ? 'open' : 'draft'
            ];
        } else {
            error_log("Lexware API HTTP Error: {$httpCode} - Response: {$response}");

            // Parse error response
            $errorData = json_decode($response, true);
            $errorMessage = "Lexware API Fehler (HTTP {$httpCode})";

            if (isset($errorData['IssueList'])) {
                $issues = array_column($errorData['IssueList'], 'source');
                $errorMessage .= " - Felder: " . implode(', ', $issues);
            }

            return [
                'success' => false,
                'error' => $errorMessage,
                'details' => $errorData
            ];
        }
    }

    /**
     * Update Appointment mit Lexware Invoice ID
     */
    private function updateAppointmentWithInvoice($appointmentId, $invoiceId)
    {
        $this->db->query("
            UPDATE appointments 
            SET 
                invoice_number = ?,
                invoice_created_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ", [$invoiceId, $appointmentId]);
    }

    /**
     * Hilfsfunktion: Extrahiere Straße aus Adresse
     */
    private function extractStreet($address)
    {
        // Versuche Straße und Hausnummer zu extrahieren
        // Format angenommen: Straße Hausnummer, PLZ Stadt
        preg_match('/^([^,]+)/', $address, $matches);
        $streetPart = trim($matches[1] ?? $address);

        // Entferne PLZ und Stadt falls vorhanden
        preg_match('/^(.+?)\s+\d{5}/', $streetPart, $streetMatches);
        if ($streetMatches) {
            return trim($streetMatches[1]);
        }

        return $streetPart;
    }

    /**
     * Hilfsfunktion: Extrahiere PLZ aus Adresse
     */
    private function extractZip($address)
    {
        // Versuche PLZ zu extrahieren (5-stellig in Deutschland)
        preg_match('/\b(\d{5})\b/', $address, $matches);
        return $matches[1] ?? '00000';
    }

    /**
     * Hilfsfunktion: Extrahiere Stadt aus Adresse
     */
    private function extractCity($address)
    {
        // Versuche Stadt nach PLZ zu extrahieren
        preg_match('/\d{5}\s+(.+?)(?:,|$)/', $address, $matches);
        return trim($matches[1] ?? 'Unbekannt');
    }

    /**
     * Hole Rechnung von Lexware
     */
    public function getInvoice($invoiceId)
    {
        if (empty($this->apiKey)) {
            throw new Exception('Lexware API Key nicht konfiguriert');
        }

        $ch = curl_init($this->apiUrl . '/invoices/' . $invoiceId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        return null;
    }

    /**
     * Download Rechnung als PDF
     */
    public function downloadInvoicePdf($invoiceId)
    {
        if (empty($this->apiKey)) {
            throw new Exception('Lexware API Key nicht konfiguriert');
        }

        // Render die Rechnung als PDF
        $ch = curl_init($this->apiUrl . '/invoices/' . $invoiceId . '/document');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/pdf'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $pdfContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return $pdfContent;
        }

        return null;
    }
}
