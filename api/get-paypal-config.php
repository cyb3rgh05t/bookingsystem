<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

$db = Database::getInstance();
$settings = $db->fetch("SELECT paypal_client_id FROM settings WHERE id = 1");

echo json_encode([
    'client_id' => $settings['paypal_client_id'] ?? null
]);
