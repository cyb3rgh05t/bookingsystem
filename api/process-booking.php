<?php

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        throw new Exception('Keine Daten empfangen');
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

    // Commit transaction
    $db->getConnection()->commit();

    // Generate booking number
    $bookingNumber = date('Y') . '-' . str_pad($appointmentId, 4, '0', STR_PAD_LEFT);

    // Send confirmation email (only if email is configured)
    $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");
    if (!empty($settings['smtp_host']) && !empty($settings['smtp_user'])) {
        // Here you would send the email
        // For now, we skip it to avoid errors
    }

    echo json_encode([
        'success' => true,
        'bookingNumber' => $bookingNumber,
        'appointmentId' => $appointmentId
    ]);
} catch (Exception $e) {
    if (isset($db)) {
        $db->getConnection()->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
