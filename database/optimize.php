<?php
require_once '../includes/config.php';

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // WAL-Modus aktivieren für bessere Concurrency
    $db->exec("PRAGMA journal_mode=WAL");

    // Indizes für häufige Abfragen
    $db->exec("CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments(appointment_date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_appointments_customer ON appointments(customer_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_services_active ON services(is_active)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_blocked_times_date ON blocked_times(date)");

    // Optimierung
    $db->exec("VACUUM");
    $db->exec("ANALYZE");

    echo "Datenbank erfolgreich optimiert!";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage();
}
