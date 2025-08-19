<?php

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    // Get month and year
    $month = $data['month'] ?? date('Y-m');

    $db = Database::getInstance();

    // Get all blocked times for this month
    $blocked = $db->fetchAll("
        SELECT date, is_full_day 
        FROM blocked_times 
        WHERE date LIKE ? 
        ORDER BY date
    ", [$month . '%']);

    // Separate fully blocked days and partially blocked days
    $fullyBlockedDays = [];
    $partiallyBlockedDays = [];

    foreach ($blocked as $block) {
        if ($block['is_full_day'] == 1) {
            $fullyBlockedDays[] = $block['date'];
        } else {
            $partiallyBlockedDays[] = $block['date'];
        }
    }

    echo json_encode([
        'success' => true,
        'fullyBlocked' => array_unique($fullyBlockedDays),
        'partiallyBlocked' => array_unique($partiallyBlockedDays)
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
