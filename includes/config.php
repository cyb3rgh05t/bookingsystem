<?php

header('Content-Type: text/html; charset=UTF-8');

// Check if mbstring is available and use it if possible
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

define('DB_PATH', __DIR__ . '/../database/booking.db');
define('SITE_URL', 'http://localhost:8000');
define('SITE_NAME', 'Auto Service Booking');

// Zeitzone
date_default_timezone_set('Europe/Berlin');

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
