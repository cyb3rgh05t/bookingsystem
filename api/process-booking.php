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
    // E-MAIL VERSAND (OPTIONAL)
    // ========================================
    $emailSent = false;
    $emailError = null;

    // Get settings to check if SMTP is configured
    $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

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
                    require_once __DIR__ . '/../includes/functions.php';

                    $emailSent = sendConfirmationEmail(
                        $data['customer']['email'],
                        $bookingNumber,
                        $data
                    );

                    if ($emailSent) {
                        error_log("✔ E-Mail mit Fallback-Funktion gesendet");
                    }
                } catch (Exception $e) {
                    error_log("Fallback E-Mail auch fehlgeschlagen: " . $e->getMessage());
                }
            } else {
                error_log("ℹ Keine E-Mail-Funktion gefunden, aber Buchung wurde gespeichert");
            }
        }
    } else {
        // No SMTP configured - that's OK!
        error_log("ℹ SMTP nicht konfiguriert - E-Mail-Versand übersprungen");
        $emailError = "SMTP nicht konfiguriert";
    }

    // ========================================
    // ANTWORT SENDEN - IMMER ERFOLGREICH!
    // ========================================

    $response = [
        'success' => true,
        'bookingNumber' => $bookingNumber,
        'appointmentId' => $appointmentId,
        'emailSent' => $emailSent
    ];

    // Optional: Add email status info (for debugging)
    if ($emailError && isset($_GET['debug'])) {
        $response['emailInfo'] = $emailError;
    }

    // Stelle sicher, dass nichts anderes ausgegeben wurde
    ob_clean();

    // Sende JSON Response
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    // Only if BOOKING failed (not email)
    if (isset($db) && $db->getConnection()->inTransaction()) {
        $db->getConnection()->rollBack();
    }

    // Clear any output
    ob_clean();

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
