<?php
header('Content-Type: text/html; charset=UTF-8');

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// Dynamischer Datenbankpfad
$dbPath = dirname(__DIR__) . '/database/booking.db';
if (!file_exists($dbPath)) {
    // Fallback für andere Strukturen
    $dbPath = __DIR__ . '/../database/booking.db';
}

define('DB_PATH', $dbPath);

// Dynamische URL-Erkennung
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $protocol . '://' . $host);
define('SITE_NAME', 'Auto Service Booking');

// Zeitzone
date_default_timezone_set('Europe/Berlin');

// Session mit error handling
if (session_status() === PHP_SESSION_NONE) {
    try {
        session_start();
    } catch (Exception $e) {
        error_log("Session start failed: " . $e->getMessage());
    }
}
