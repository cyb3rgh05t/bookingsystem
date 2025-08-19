<?php

require_once '../includes/config.php';

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tabellen erstellen
    $sql = "
    -- Firmen-Einstellungen
    CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY,
        company_name TEXT,
        address TEXT,
        phone TEXT,
        email TEXT,
        google_maps_api_key TEXT,
        paypal_client_id TEXT,
        smtp_host TEXT,
        smtp_port INTEGER,
        smtp_user TEXT,
        smtp_password TEXT,
        max_distance INTEGER DEFAULT 35,
        min_price_above_10km REAL DEFAULT 59.90,
        price_per_km REAL DEFAULT 2.0,
        free_distance_km INTEGER DEFAULT 10,
        working_hours_weekday_start TEXT DEFAULT '16:30',
        working_hours_weekday_end TEXT DEFAULT '21:00',
        working_hours_saturday_start TEXT DEFAULT '09:00',
        working_hours_saturday_end TEXT DEFAULT '14:00',
        time_slot_duration INTEGER DEFAULT 30
    );

    CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        price REAL NOT NULL,
        duration_minutes INTEGER NOT NULL,
        background_image TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL,
        address TEXT NOT NULL,
        car_brand TEXT,
        car_model TEXT,
        car_year INTEGER,
        license_plate TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS appointments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        total_duration INTEGER NOT NULL,
        distance_km REAL,
        travel_cost REAL DEFAULT 0,
        subtotal REAL NOT NULL,
        total_price REAL NOT NULL,
        status TEXT DEFAULT 'pending',
        payment_status TEXT DEFAULT 'unpaid',
        payment_method TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id)
    );

    CREATE TABLE IF NOT EXISTS appointment_services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        appointment_id INTEGER,
        service_id INTEGER,
        price REAL NOT NULL,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id),
        FOREIGN KEY (service_id) REFERENCES services(id)
    );

    CREATE TABLE IF NOT EXISTS blocked_times (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date DATE NOT NULL,
        start_time TIME,
        end_time TIME,
        reason TEXT,
        is_full_day BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        email TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    ";

    $db->exec($sql);

    // Standard-Einstellungen einfügen
    $checkSettings = $db->query("SELECT COUNT(*) as count FROM settings")->fetch();
    if ($checkSettings['count'] == 0) {
        $db->exec("INSERT INTO settings (id, company_name, address, email, phone) 
                   VALUES (1, 'Auto Service GmbH', 'Musterstraße 1, 12345 Musterstadt', 'info@autoservice.de', '0123-456789')");
    }

    // Standard-Admin erstellen (Passwort: admin123)
    $checkAdmin = $db->query("SELECT COUNT(*) as count FROM admin_users")->fetch();
    if ($checkAdmin['count'] == 0) {
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO admin_users (username, password_hash, email) 
                   VALUES ('admin', '$passwordHash', 'admin@autoservice.de')");
    }

    // Beispiel-Services einfügen
    $checkServices = $db->query("SELECT COUNT(*) as count FROM services")->fetch();
    if ($checkServices['count'] == 0) {
        $services = [
            ['Ölwechsel', 'Kompletter Ölwechsel inkl. Filter', 49.90, 30, 'oil-change.jpg'],
            ['Reifenwechsel', 'Wechsel von Sommer- auf Winterreifen oder umgekehrt', 39.90, 45, 'tire-change.jpg'],
            ['Bremsen-Check', 'Überprüfung der Bremsanlage', 29.90, 30, 'brake-check.jpg'],
            ['Klimaanlagen-Service', 'Wartung und Desinfektion der Klimaanlage', 89.90, 60, 'ac-service.jpg'],
            ['Batterie-Check', 'Überprüfung und ggf. Wechsel der Batterie', 19.90, 20, 'battery-check.jpg'],
            ['Inspektion Klein', 'Kleine Inspektion nach Herstellervorgaben', 149.90, 90, 'inspection-small.jpg'],
            ['Inspektion Groß', 'Große Inspektion nach Herstellervorgaben', 299.90, 120, 'inspection-large.jpg']
        ];

        $stmt = $db->prepare("INSERT INTO services (name, description, price, duration_minutes, background_image) 
                             VALUES (?, ?, ?, ?, ?)");
        foreach ($services as $service) {
            $stmt->execute($service);
        }
    }

    echo "Datenbank erfolgreich eingerichtet!";
} catch (PDOException $e) {
    echo "Fehler beim Einrichten der Datenbank: " . $e->getMessage();
}
