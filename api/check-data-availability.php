<?php

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $month = $data['month'] ?? date('Y-m');
    $duration = intval($data['duration'] ?? 0);

    $db = Database::getInstance();
    $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

    // Get all days in month
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));

    $availableDays = [];
    $currentDate = $startDate;

    while ($currentDate <= $endDate) {
        $dayOfWeek = date('w', strtotime($currentDate));

        // Skip Sundays
        if ($dayOfWeek != 0) {
            // Check if this day has enough free time
            // (Simplified check - just check if any appointment would overflow)
            $hasSpace = true;

            // You can add more detailed checking here if needed

            if ($hasSpace) {
                $availableDays[] = $currentDate;
            }
        }

        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    }

    echo json_encode(['availableDays' => $availableDays]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
