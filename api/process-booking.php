<?php
// WICHTIG: Absolut NICHTS vor diesem <?php Tag!

// Verhindere jegliche ungewollte Ausgabe
ob_start();
ob_clean();

// Setze korrekten Content-Type
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once '../includes/config.php';
require_once '../includes/db.php';

try {
    // Hole und validiere Input
    $rawInput = file_get_contents('php://input');
    if (!$rawInput) {
        throw new Exception('Keine Daten empfangen');
    }

    $data = json_decode($rawInput, true);
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Ungültige JSON-Daten: ' . json_last_error_msg());
    }

    $db = Database::getInstance();

    // Start transaction
    $db->getConnection()->beginTransaction();

    // Insert customer
    $db->query("
        INSERT INTO customers (first_name, last_name, email, phone, address, car_brand, car_model, car_year, license_plate)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $data['customer']['first_name'],
        $data['customer']['last_name'],
        $data['customer']['email'],
        $data['customer']['phone'],
        $data['customer']['address'],
        $data['customer']['car_brand'] ?? null,
        $data['customer']['car_model'] ?? null,
        $data['customer']['car_year'] ?? null,
        $data['customer']['license_plate'] ?? null
    ]);

    $customerId = $db->lastInsertId();

    // Calculate total duration
    $totalDuration = array_reduce($data['services'], function ($sum, $service) {
        return $sum + $service['duration_minutes'];
    }, 0);

    // Insert appointment
    $db->query("
        INSERT INTO appointments (
            customer_id, appointment_date, appointment_time, total_duration,
            distance_km, travel_cost, subtotal, total_price, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ", [
        $customerId,
        $data['date'],
        $data['time'],
        $totalDuration,
        $data['distance'],
        $data['travelCost'],
        $data['subtotal'],
        $data['total']
    ]);

    $appointmentId = $db->lastInsertId();

    // Insert appointment services
    foreach ($data['services'] as $service) {
        $db->query("
            INSERT INTO appointment_services (appointment_id, service_id, price)
            VALUES (?, ?, ?)
        ", [
            $appointmentId,
            $service['id'],
            $service['price']
        ]);
    }

    // Commit transaction - BOOKING IS SAVED!
    $db->getConnection()->commit();

    // Generate booking number
    $bookingNumber = date('Y') . '-' . str_pad($appointmentId, 4, '0', STR_PAD_LEFT);

    // ========================================
    // LEXWARE RECHNUNG ERSTELLEN
    // ========================================
    $lexwareCreated = false;
    $lexwareError = null;
    $invoiceNumber = null;
    $invoiceId = null;
    $pdfUrl = null;

    // Get settings to check if Lexware is configured
    $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

    // Prüfe ob Lexware konfiguriert ist
    if (!empty($settings['lexware_api_key'])) {
        try {
            // Prüfe ob Lexware API Datei existiert
            $lexwareApiPath = __DIR__ . '/lexware-invoice.php';

            if (file_exists($lexwareApiPath)) {
                // Lade die Lexware API Klasse
                require_once $lexwareApiPath;

                $lexwareApi = new LexwareAPI();

                // Erstelle Rechnung direkt nach Buchung
                // Status wird auf "unpaid" gesetzt statt "paid"
                $lexwareResponse = $lexwareApi->createInvoice($appointmentId, [
                    'payment_method' => 'pending',
                    'payment_status' => 'unpaid',
                    'transaction_id' => null
                ]);

                if ($lexwareResponse['success']) {
                    $lexwareCreated = true;
                    $invoiceNumber = $lexwareResponse['invoice_number'];
                    $invoiceId = $lexwareResponse['invoice_id'] ?? null;
                    $pdfUrl = $lexwareResponse['pdf_url'] ?? null;
                    error_log("✔ Lexware Rechnung erstellt: " . $invoiceNumber);
                } else {
                    $lexwareError = $lexwareResponse['error'];
                    error_log("⚠ Lexware Fehler: " . $lexwareError);
                }
            } else {
                error_log("Lexware API Datei nicht gefunden: " . $lexwareApiPath);
                $lexwareError = "Lexware API nicht verfügbar";
            }
        } catch (Exception $e) {
            $lexwareError = $e->getMessage();
            error_log("Lexware Exception: " . $lexwareError);
            // Fehler bei Lexware soll die Buchung nicht verhindern
        }
    } else {
        error_log("Lexware API Key nicht konfiguriert");
    }

    // ========================================
    // E-MAIL VERSAND (OPTIONAL)
    // ========================================
    $emailSent = false;
    $emailError = null;

    // Check if SMTP is configured
    if (!empty($settings['smtp_host']) && !empty($settings['smtp_user']) && !empty($settings['smtp_password'])) {

        // Check if mail_smtp.php exists
        $mailSmtpPath = __DIR__ . '/../includes/mail_smtp.php';

        if (file_exists($mailSmtpPath)) {
            // File exists, try to send email
            try {
                require_once $mailSmtpPath;

                // Prepare booking data for email
                $emailData = [
                    'customer' => $data['customer'],
                    'services' => $data['services'],
                    'date' => $data['date'],
                    'time' => $data['time'],
                    'distance' => $data['distance'],
                    'travelCost' => $data['travelCost'],
                    'subtotal' => $data['subtotal'],
                    'total' => $data['total']
                ];

                // Try to send email
                $emailSent = sendConfirmationEmailSMTP(
                    $data['customer']['email'],
                    $bookingNumber,
                    $emailData
                );

                if ($emailSent) {
                    error_log("✔ E-Mail erfolgreich gesendet an: " . $data['customer']['email']);
                } else {
                    error_log("⚠ E-Mail konnte nicht gesendet werden, aber Buchung wurde gespeichert");
                    $emailError = "E-Mail konnte nicht gesendet werden";
                }
            } catch (Exception $mailException) {
                // Email failed, but don't stop the booking
                error_log("E-Mail Fehler: " . $mailException->getMessage());
                $emailError = "E-Mail-Versand fehlgeschlagen: " . $mailException->getMessage();
            }
        } else {
            // mail_smtp.php doesn't exist - try fallback with old mail.php
            $mailPath = __DIR__ . '/../includes/mail.php';

            if (file_exists($mailPath)) {
                try {
                    require_once $mailPath;
                    require_once __DIR__ . '/../includes/config.php';
                    require_once __DIR__ . '/../includes/db.php';

                    $emailData = [
                        'customer' => $data['customer'],
                        'services' => $data['services'],
                        'date' => $data['date'],
                        'time' => $data['time'],
                        'distance' => $data['distance'],
                        'travelCost' => $data['travelCost'],
                        'subtotal' => $data['subtotal'],
                        'total' => $data['total']
                    ];

                    $emailSent = sendConfirmationEmail(
                        $data['customer']['email'],
                        $bookingNumber,
                        $emailData
                    );

                    if ($emailSent) {
                        error_log("✔ Fallback E-Mail gesendet");
                    } else {
                        error_log("⚠ Fallback E-Mail fehlgeschlagen");
                        $emailError = "E-Mail konnte nicht gesendet werden";
                    }
                } catch (Exception $e) {
                    error_log("Fallback E-Mail Exception: " . $e->getMessage());
                    $emailError = $e->getMessage();
                }
            } else {
                // No mail system found
                error_log("Keine E-Mail-Konfiguration gefunden (weder mail_smtp.php noch mail.php)");
                $emailError = "E-Mail-System nicht konfiguriert";
            }
        }
    } else {
        error_log("SMTP nicht konfiguriert in den Einstellungen");
        $emailError = "SMTP nicht konfiguriert";
    }

    // Erfolgreiche Response mit allen Informationen
    ob_clean();
    echo json_encode([
        'success' => true,
        'appointmentId' => $appointmentId,
        'bookingNumber' => $bookingNumber,
        'emailSent' => $emailSent,
        'emailError' => $emailError,
        'lexwareCreated' => $lexwareCreated,
        'invoiceNumber' => $invoiceNumber,
        'invoiceId' => $invoiceId,
        'pdfUrl' => $pdfUrl,
        'lexwareError' => $lexwareError,
        'message' => 'Buchung erfolgreich gespeichert' .
            ($lexwareCreated ? ' und Rechnung erstellt' : '') .
            ($emailSent ? ' und E-Mail versendet' : '')
    ]);
} catch (Exception $e) {
    // Rollback bei Fehler
    if (isset($db) && $db->getConnection()->inTransaction()) {
        $db->getConnection()->rollBack();
    }

    // Saubere Fehlerausgabe
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

// Stelle sicher, dass nichts nach dem JSON ausgegeben wird
exit();
