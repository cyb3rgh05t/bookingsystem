<?php

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

try {
    $db = Database::getInstance();
    $services = $db->fetchAll("SELECT * FROM services WHERE is_active = 1 ORDER BY name");
    echo json_encode($services);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
