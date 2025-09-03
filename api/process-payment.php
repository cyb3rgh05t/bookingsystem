<?php

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

        // PayPal Access Token
        $baseUrl = $settings['paypal_mode'] === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        $debugInfo['paypal_base_url'] = $baseUrl;

        // Get Access Token
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

        $debugInfo['token_http_code'] = $tokenHttpCode;

        if ($curlError) {
            $debugInfo['curl_error'] = $curlError;
            throw new Exception('cURL Error: ' . $curlError);
        }

        if ($tokenHttpCode !== 200) {
            $debugInfo['token_response'] = $tokenResponse;
            throw new Exception('PayPal Auth fehlgeschlagen. HTTP Code: ' . $tokenHttpCode);
        }

        $tokenData = json_decode($tokenResponse, true);
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            throw new Exception('Access Token konnte nicht abgerufen werden');
        }

        $debugInfo['token_received'] = true;

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

        $debugInfo['order_data'] = $orderData;

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
        $curlError = curl_error($ch);
        curl_close($ch);

        $debugInfo['order_http_code'] = $orderHttpCode;

        if ($curlError) {
            $debugInfo['order_curl_error'] = $curlError;
            throw new Exception('Order cURL Error: ' . $curlError);
        }

        $orderData = json_decode($orderResponse, true);
        $debugInfo['order_response'] = $orderData;

        if ($orderHttpCode !== 201 && $orderHttpCode !== 200) {
            $errorMessage = 'PayPal Order Creation Failed. HTTP: ' . $orderHttpCode;
            if (isset($orderData['message'])) {
                $errorMessage .= ' - ' . $orderData['message'];
            }
            if (isset($orderData['details'])) {
                $debugInfo['error_details'] = $orderData['details'];
            }
            throw new Exception($errorMessage);
        }

        if (!isset($orderData['id'])) {
            throw new Exception('Order ID nicht in PayPal Response');
        }

        // Success!
        ob_clean();
        echo json_encode([
            'success' => true,
            'order_id' => $orderData['id'],
            'approval_url' => $orderData['links'][1]['href'] ?? null,
            'debug' => $DEBUG ? $debugInfo : null
        ]);
        exit;
    }

    throw new Exception('Unbekannte Action: ' . $action);
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
