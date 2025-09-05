<?php

/**
 * PayPal Payment Processing OHNE doppelte Lexware Rechnungserstellung
 * Korrigierte Version - nutzt die bereits existierende Rechnung
 */

ob_start();
ob_clean();

$DEBUG = false;

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once '../includes/config.php';
require_once '../includes/db.php';

$debugInfo = [];

try {
    $db = Database::getInstance();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    $debugInfo['action'] = $action;
    $debugInfo['post_data'] = $_POST;

    // Hole Settings
    $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

    $debugInfo['paypal_client_id'] = substr($settings['paypal_client_id'] ?? '', 0, 10) . '...';
    $debugInfo['paypal_mode'] = $settings['paypal_mode'] ?? 'sandbox';

    if (!$settings['paypal_client_id'] || !$settings['paypal_client_secret']) {
        throw new Exception('PayPal Credentials fehlen in Einstellungen');
    }

    // PayPal Base URL
    $baseUrl = $settings['paypal_mode'] === 'sandbox'
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';

    // ========================================
    // CREATE ORDER
    // ========================================
    if ($action === 'create_order') {
        $appointmentId = $_POST['appointment_id'] ?? null;

        if (!$appointmentId) {
            throw new Exception('Appointment ID fehlt');
        }

        $debugInfo['appointment_id'] = $appointmentId;

        // Hole Buchungsdaten
        $appointment = $db->fetch("
            SELECT a.*, c.email, c.first_name, c.last_name
            FROM appointments a
            JOIN customers c ON a.customer_id = c.id
            WHERE a.id = ?
        ", [$appointmentId]);

        if (!$appointment) {
            throw new Exception('Buchung mit ID ' . $appointmentId . ' nicht gefunden');
        }

        $debugInfo['appointment_found'] = true;
        $debugInfo['total_price'] = $appointment['total_price'];

        // Get PayPal Access Token
        $ch = curl_init($baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $settings['paypal_client_id'] . ':' . $settings['paypal_client_secret']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: de_DE'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $tokenResponse = curl_exec($ch);
        $tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($tokenHttpCode !== 200) {
            throw new Exception('PayPal Auth fehlgeschlagen. HTTP Code: ' . $tokenHttpCode);
        }

        $tokenData = json_decode($tokenResponse, true);
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            throw new Exception('Access Token konnte nicht abgerufen werden');
        }

        // Erstelle Order
        $bookingNumber = date('Y') . '-' . str_pad($appointmentId, 4, '0', STR_PAD_LEFT);

        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $bookingNumber,
                'description' => 'Auto-Service Buchung ' . $bookingNumber,
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => number_format($appointment['total_price'], 2, '.', '')
                ]
            ]],
            'application_context' => [
                'brand_name' => 'Auto Service',
                'locale' => 'de-DE',
                'landing_page' => 'BILLING',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => 'http://localhost:5000/payment-success.php',
                'cancel_url' => 'http://localhost:5000/booking.php'
            ]
        ];

        // Create Order API Call
        $ch = curl_init($baseUrl . '/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
            'PayPal-Request-Id: ' . uniqid()
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $orderResponse = curl_exec($ch);
        $orderHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $orderData = json_decode($orderResponse, true);

        if ($orderHttpCode !== 201) {
            throw new Exception('PayPal Order Creation Failed. HTTP: ' . $orderHttpCode);
        }

        if (!isset($orderData['id'])) {
            throw new Exception('Order ID nicht in PayPal Response');
        }

        // Speichere PayPal Order ID in DB
        $db->query("
            UPDATE appointments 
            SET paypal_order_id = ?
            WHERE id = ?
        ", [$orderData['id'], $appointmentId]);

        // Success
        ob_clean();
        echo json_encode([
            'success' => true,
            'order_id' => $orderData['id']
        ]);
        exit;
    }

    // ========================================
    // CAPTURE PAYMENT - KORRIGIERT: Keine neue Rechnung!
    // ========================================
    elseif ($action === 'capture_payment') {
        $orderId = $_POST['order_id'] ?? null;

        if (!$orderId) {
            throw new Exception('Order ID fehlt');
        }

        $debugInfo['order_id'] = $orderId;

        // Hole Appointment basierend auf PayPal Order ID
        $appointment = $db->fetch("
            SELECT a.*, c.email, c.first_name, c.last_name
            FROM appointments a
            JOIN customers c ON a.customer_id = c.id
            WHERE a.paypal_order_id = ?
        ", [$orderId]);

        if (!$appointment) {
            throw new Exception('Buchung für Order ID ' . $orderId . ' nicht gefunden');
        }

        // Get PayPal Access Token
        $ch = curl_init($baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $settings['paypal_client_id'] . ':' . $settings['paypal_client_secret']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: de_DE'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $tokenResponse = curl_exec($ch);
        $tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($tokenHttpCode !== 200) {
            throw new Exception('PayPal Auth fehlgeschlagen');
        }

        $tokenData = json_decode($tokenResponse, true);
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            throw new Exception('Access Token konnte nicht abgerufen werden');
        }

        // Capture Payment
        $ch = curl_init($baseUrl . '/v2/checkout/orders/' . $orderId . '/capture');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}'); // Empty body für capture
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $captureResponse = curl_exec($ch);
        $captureHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $captureData = json_decode($captureResponse, true);

        if ($captureHttpCode !== 201 && $captureHttpCode !== 200) {
            $errorMsg = 'PayPal Capture Failed. HTTP: ' . $captureHttpCode;
            if (isset($captureData['details'][0]['description'])) {
                $errorMsg .= ' - ' . $captureData['details'][0]['description'];
            }
            throw new Exception($errorMsg);
        }

        // Hole Capture ID
        $captureId = null;
        if (isset($captureData['purchase_units'][0]['payments']['captures'][0]['id'])) {
            $captureId = $captureData['purchase_units'][0]['payments']['captures'][0]['id'];
        }

        // Update Appointment mit Payment Info
        $db->query("
            UPDATE appointments 
            SET 
                payment_status = 'paid',
                payment_method = 'paypal',
                payment_date = CURRENT_TIMESTAMP,
                paypal_capture_id = ?
            WHERE id = ?
        ", [$captureId, $appointment['id']]);

        // Speichere Payment History
        $db->query("
            INSERT INTO payment_history 
            (appointment_id, payment_method, amount, currency, transaction_id, status, gateway_response)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [
            $appointment['id'],
            'paypal',
            $appointment['total_price'],
            'EUR',
            $captureId,
            'completed',
            json_encode($captureData)
        ]);

        // ========================================
        // WICHTIG: KEINE NEUE RECHNUNG ERSTELLEN!
        // Nutze die bereits existierende Rechnung
        // ========================================

        $invoiceExists = false;
        $invoiceNumber = null;
        $lexwareMessage = null;

        // Prüfe ob bereits eine Rechnung existiert
        if (!empty($appointment['lexware_invoice_id'])) {
            $invoiceExists = true;
            $invoiceNumber = $appointment['invoice_number'];
            $lexwareMessage = 'Existierende Rechnung verwendet';

            error_log("✅ Zahlung erfasst für existierende Rechnung: " . $invoiceNumber);

            // Optional: Du könntest hier die Lexware API nutzen um den 
            // Status der Rechnung auf "bezahlt" zu setzen, falls die API das unterstützt
            // Aber KEINE neue Rechnung erstellen!

        } else {
            // Falls aus irgendeinem Grund keine Rechnung existiert 
            // (sollte nicht passieren, da bei Buchung erstellt wird)
            $lexwareMessage = 'Keine Rechnung vorhanden (wurde bei Buchung erstellt?)';
            error_log("⚠️ Warnung: Keine Lexware Rechnung für Appointment " . $appointment['id'] . " gefunden");
        }

        // Success Response
        ob_clean();
        echo json_encode([
            'success' => true,
            'capture_id' => $captureId,
            'payment_status' => 'paid',
            'invoice_exists' => $invoiceExists,
            'invoice_number' => $invoiceNumber,
            'lexware_message' => $lexwareMessage
        ]);
        exit;
    }

    // Unbekannte Action
    else {
        throw new Exception('Unbekannte Action: ' . $action);
    }
} catch (Exception $e) {
    ob_clean();

    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];

    if ($DEBUG) {
        $response['debug'] = $debugInfo;
    }

    http_response_code(400);
    echo json_encode($response);
    exit;
}
