<?php

/**
 * Lexware API Integration für automatische Rechnungserstellung
 * Wird nach erfolgreicher PayPal-Zahlung aufgerufen
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

        // Lexware API Credentials aus Settings
        $this->apiUrl = $settings['lexware_api_url'] ?? 'https://api.lexware.de/v1';
        $this->apiKey = $settings['lexware_api_key'] ?? '';
    }

    /**
     * Erstellt eine Rechnung in Lexware nach erfolgreicher Zahlung
     */
    public function createInvoice($appointmentId, $paymentData = [])
    {
        try {
            // Hole Buchungsdaten aus der Datenbank
            $appointment = $this->getAppointmentData($appointmentId);

            if (!$appointment) {
                throw new Exception('Buchung nicht gefunden');
            }

            // Bereite Rechnungsdaten für Lexware vor
            $invoiceData = $this->prepareInvoiceData($appointment, $paymentData);

            // Sende Rechnung an Lexware API
            $lexwareResponse = $this->sendToLexware($invoiceData);

            // Speichere Lexware Rechnungsnummer in der Datenbank
            if ($lexwareResponse['success']) {
                $this->updateAppointmentWithInvoice($appointmentId, $lexwareResponse['invoice_number']);
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
     * Bereite Rechnungsdaten für Lexware API vor
     */
    private function prepareInvoiceData($appointment, $paymentData)
    {
        $settings = $this->db->fetch("SELECT * FROM settings WHERE id = 1");

        // Generiere Buchungsnummer
        $bookingNumber = date('Y', strtotime($appointment['created_at'])) . '-' . str_pad($appointment['id'], 4, '0', STR_PAD_LEFT);

        // Rechnungspositionen vorbereiten
        $positions = [];
        $positionNumber = 1;

        // Services als Rechnungspositionen
        foreach ($appointment['services'] as $service) {
            $positions[] = [
                'positionNumber' => $positionNumber++,
                'articleNumber' => 'SERVICE-' . $positionNumber,
                'description' => $service['name'] . "\n" . $service['description'],
                'quantity' => 1,
                'unit' => 'Stück',
                'unitPrice' => [
                    'amount' => $service['price'],
                    'currency' => 'EUR',
                    'taxRate' => 19 // Standard MwSt-Satz
                ],
                'totalPrice' => [
                    'amount' => $service['price'],
                    'currency' => 'EUR'
                ]
            ];
        }

        // Anfahrtskosten als Position hinzufügen
        if ($appointment['travel_cost'] > 0) {
            $positions[] = [
                'positionNumber' => $positionNumber++,
                'articleNumber' => 'TRAVEL',
                'description' => sprintf('Anfahrtskosten (%.1f km)', $appointment['distance_km']),
                'quantity' => 1,
                'unit' => 'Pauschale',
                'unitPrice' => [
                    'amount' => $appointment['travel_cost'],
                    'currency' => 'EUR',
                    'taxRate' => 19
                ],
                'totalPrice' => [
                    'amount' => $appointment['travel_cost'],
                    'currency' => 'EUR'
                ]
            ];
        }

        // Lexware Rechnungsobjekt
        $invoice = [
            'invoiceNumber' => null, // Wird von Lexware generiert
            'invoiceDate' => date('Y-m-d'),
            'performanceDate' => $appointment['appointment_date'],
            'dueDate' => date('Y-m-d', strtotime('+14 days')), // 14 Tage Zahlungsziel
            'customer' => [
                'customerNumber' => $this->getOrCreateCustomerNumber($appointment),
                'name' => $appointment['first_name'] . ' ' . $appointment['last_name'],
                'firstName' => $appointment['first_name'],
                'lastName' => $appointment['last_name'],
                'email' => $appointment['email'],
                'phone' => $appointment['phone'],
                'address' => [
                    'street' => $this->extractStreet($appointment['address']),
                    'zip' => $this->extractZip($appointment['address']),
                    'city' => $this->extractCity($appointment['address']),
                    'country' => 'DE'
                ]
            ],
            'positions' => $positions,
            'totalAmount' => [
                'amount' => $appointment['total_price'],
                'currency' => 'EUR'
            ],
            'paymentMethod' => $paymentData['payment_method'] ?? 'PayPal',
            'paymentStatus' => 'paid',
            'paymentDate' => date('Y-m-d'),
            'paymentReference' => $paymentData['transaction_id'] ?? '',
            'internalNote' => sprintf(
                "Buchungsnummer: %s\nTermin: %s %s\nFahrzeug: %s %s (%s)\nKennzeichen: %s",
                $bookingNumber,
                $appointment['appointment_date'],
                $appointment['appointment_time'],
                $appointment['car_brand'] ?? '-',
                $appointment['car_model'] ?? '-',
                $appointment['car_year'] ?? '-',
                $appointment['license_plate'] ?? '-'
            ),
            'customerNote' => sprintf(
                "Vielen Dank für Ihren Auftrag!\nBuchungsnummer: %s\nTermin: %s um %s Uhr",
                $bookingNumber,
                date('d.m.Y', strtotime($appointment['appointment_date'])),
                $appointment['appointment_time']
            )
        ];

        return $invoice;
    }

    /**
     * Sende Rechnung an Lexware API
     */
    private function sendToLexware($invoiceData)
    {
        if (empty($this->apiKey)) {
            throw new Exception('Lexware API Key nicht konfiguriert');
        }

        // API Request vorbereiten
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
        curl_close($ch);

        // Response verarbeiten
        if ($httpCode === 201 || $httpCode === 200) {
            $responseData = json_decode($response, true);

            return [
                'success' => true,
                'invoice_number' => $responseData['invoiceNumber'] ?? null,
                'invoice_id' => $responseData['id'] ?? null,
                'pdf_url' => $responseData['pdfUrl'] ?? null
            ];
        } else {
            error_log("Lexware API HTTP Error: {$httpCode} - Response: {$response}");

            return [
                'success' => false,
                'error' => "Lexware API Fehler (HTTP {$httpCode})",
                'details' => json_decode($response, true)
            ];
        }
    }

    /**
     * Update Appointment mit Lexware Rechnungsnummer
     */
    private function updateAppointmentWithInvoice($appointmentId, $invoiceNumber)
    {
        $this->db->query("
            UPDATE appointments 
            SET 
                invoice_number = ?,
                invoice_created_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ", [$invoiceNumber, $appointmentId]);
    }

    /**
     * Hole oder erstelle Kundennummer für Lexware
     */
    private function getOrCreateCustomerNumber($appointment)
    {
        // Prüfe ob Kunde bereits eine Lexware Kundennummer hat
        $existing = $this->db->fetch("
            SELECT lexware_customer_number 
            FROM customers 
            WHERE id = ?
        ", [$appointment['customer_id']]);

        if ($existing && !empty($existing['lexware_customer_number'])) {
            return $existing['lexware_customer_number'];
        }

        // Erstelle neue Kundennummer (Format: K-00001)
        $lastNumber = $this->db->fetch("
            SELECT MAX(CAST(SUBSTR(lexware_customer_number, 3) AS INTEGER)) as max_num
            FROM customers
            WHERE lexware_customer_number LIKE 'K-%'
        ");

        $newNumber = 'K-' . str_pad(($lastNumber['max_num'] ?? 0) + 1, 5, '0', STR_PAD_LEFT);

        // Speichere Kundennummer
        $this->db->query("
            UPDATE customers 
            SET lexware_customer_number = ?
            WHERE id = ?
        ", [$newNumber, $appointment['customer_id']]);

        return $newNumber;
    }

    /**
     * Hilfsfunktionen zum Extrahieren von Adressdaten
     */
    private function extractStreet($address)
    {
        // Extrahiere Straße und Hausnummer aus Adresse
        preg_match('/^([^,\d]+\s*\d+[a-zA-Z]?)/', $address, $matches);
        return $matches[1] ?? $address;
    }

    private function extractZip($address)
    {
        // Extrahiere PLZ aus Adresse
        preg_match('/\b(\d{5})\b/', $address, $matches);
        return $matches[1] ?? '';
    }

    private function extractCity($address)
    {
        // Extrahiere Stadt aus Adresse
        preg_match('/\d{5}\s+(.+)$/', $address, $matches);
        return trim($matches[1] ?? '');
    }
}

// ========================================
// API ENDPOINT HANDLER
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['appointment_id'])) {
            throw new Exception('appointment_id fehlt');
        }

        $lexware = new LexwareAPI();
        $result = $lexware->createInvoice(
            $data['appointment_id'],
            $data['payment_data'] ?? []
        );

        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
