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
     * Bereite Rechnungsdaten für Lexware API vor - NEUE STRUKTUR!
     */
    private function prepareInvoiceData($appointment, $paymentData = [])
    {
        // Generiere Buchungsnummer
        $bookingNumber = date('Y') . '-' . str_pad($appointment['id'], 4, '0', STR_PAD_LEFT);

        // Bereite Line Items vor
        $lineItems = [];

        // Services als Line Items
        foreach ($appointment['services'] as $service) {
            $lineItems[] = [
                'type' => 'custom',
                'name' => $service['name'],
                'description' => $service['description'],
                'quantity' => 1,
                'unitName' => 'Stück',
                'unitPrice' => [
                    'currency' => 'EUR',
                    'netAmount' => round($service['price'] / 1.19, 2), // Netto-Betrag bei 19% MwSt
                    'taxRatePercentage' => 19
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
                    'netAmount' => round($appointment['travel_cost'] / 1.19, 2),
                    'taxRatePercentage' => 19
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
            'voucherDate' => date('c'), // ISO 8601 Format

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

            // Steuerbedingungen
            'taxConditions' => [
                'taxType' => 'net'  // Netto-Rechnung
            ],

            // Zahlungsbedingungen
            'paymentConditions' => [
                'paymentTermLabel' => '14 Tage netto',
                'paymentTermDuration' => 14
            ],

            // Lieferbedingungen (Termin)
            'shippingConditions' => [
                'shippingDate' => $appointment['appointment_date'] . 'T00:00:00.000+01:00', // Lexware-Format
                'shippingType' => 'service'  // Service statt delivery
            ],

            // Texte
            'title' => 'Rechnung',
            'introduction' => sprintf(
                'Rechnung für Ihre Buchung %s vom %s',
                $bookingNumber,
                date('d.m.Y')
            ),
            'remark' => sprintf(
                "Vielen Dank für Ihren Auftrag!\n\nTermin: %s um %s Uhr\nBuchungsnummer: %s",
                date('d.m.Y', strtotime($appointment['appointment_date'])),
                $appointment['appointment_time'],
                $bookingNumber
            )
        ];

        // Optional: Rechnungsnummer setzen (wenn gewünscht)
        // $invoice['voucherNumber'] = $bookingNumber;

        return $invoice;
    }

    /**
     * Sende Rechnung an Lexware API - KORREKTER ENDPOINT!
     */
    private function sendToLexware($invoiceData)
    {
        if (empty($this->apiKey)) {
            throw new Exception('Lexware API Key nicht konfiguriert');
        }

        // API Request vorbereiten - RICHTIGER ENDPOINT: /invoices
        $ch = curl_init($this->apiUrl . '/invoices');
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
                'resource_uri' => $responseData['resourceUri'] ?? null
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
